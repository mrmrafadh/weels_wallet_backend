<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyDelivery;
use Illuminate\Support\Facades\DB;

class DailyDeliveryController extends Controller
{
    public function submitDailySheet(Request $request)
    {
        $request->validate([
            'rider_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.fee' => 'required|numeric|min:0',
            'records.*.comm' => 'required|numeric|min:0',
            'records.*.svc' => 'required|numeric|min:0',
        ]);

        // 1. Initialize Totals
        $totalDeliveryFee = 0;
        $totalRestaurantComm = 0;
        $totalSvc = 0;
        
        $totalAdminCommDelivery = 0;
        $totalAdminCommSvc = 0;

        $processedRecords = [];

        // 2. Loop and Calculate
        foreach ($request->records as $record) {
            $fee = (float)$record['fee'];
            $comm = (float)$record['comm'];
            $svc = (float)$record['svc'];

            // A. Admin Share from Delivery Fee (Rule: <=300 is 10, else 10%)
            $adminFee = ($fee < 300) ? 10.0 : ($fee * 0.10);

            // B. Admin Share from SVC (Rule: Fixed Tiers)
            $adminSvc = 0;
            if ($svc == 50) $adminSvc = 25.0;
            elseif ($svc == 80) $adminSvc = 25.0;
            elseif ($svc == 120) $adminSvc = 60.0;
            elseif ($svc == 180) $adminSvc = 100.0;
            
            // Accumulate Totals
            $totalDeliveryFee += $fee;
            $totalRestaurantComm += $comm;
            $totalSvc += $svc;

            $totalAdminCommDelivery += $adminFee;
            $totalAdminCommSvc += $adminSvc;

            $processedRecords[] = [
                'fee' => $fee,
                'comm' => $comm,
                'svc' => $svc,
                'admin_fee' => $adminFee,
                'admin_svc' => $adminSvc
            ];
        }

        // 3. Final Calculations
        
        // UPDATE: Admin Commission = (Admin Share of Fee) + (Admin Share of SVC) + (Full Restaurant Comm)
        $totalAdminCommission = $totalAdminCommDelivery + $totalAdminCommSvc + $totalRestaurantComm;
        
        // Rider Earnings = (Fee - AdminFee) + (Svc - AdminSvc)
        // Note: We do NOT subtract Restaurant Comm here because the rider never "owned" that money.
        $riderActualEarnings = ($totalDeliveryFee - $totalAdminCommDelivery) + ($totalSvc - $totalAdminCommSvc);

        $grossEarnings = $totalDeliveryFee + $totalSvc + $totalRestaurantComm; 

        // 4. Save to Database
        $dailySheet = DailyDelivery::updateOrCreate(
            [
                'rider_id' => $request->rider_id,
                'delivery_date' => $request->date,
            ],
            [
                'records_json' => $processedRecords,
                'total_deliveryfee' => $totalDeliveryFee,
                'total_restaurantcomm' => $totalRestaurantComm,
                'total_svc' => $totalSvc,
                'admin_comm_delivery' => $totalAdminCommDelivery,
                'admin_comm_svc' => $totalAdminCommSvc,
                'admin_comm_restaurantcomm' => $totalRestaurantComm, // Saved for record keeping
                'total_earnings' => $grossEarnings,
                'admin_commission' => $totalAdminCommission, // <--- Now includes Rest. Comm
                'actual_earnings' => $riderActualEarnings,
                'status' => 'pending'
            ]
        );

        return response()->json([
            'message' => 'Daily sheet saved successfully',
            'data' => $dailySheet
        ]);
    }

    // Add this new function
    public function getDailySheet(Request $request)
    {
        $request->validate([
            'rider_id' => 'required',
            'date' => 'required|date',
        ]);

        $sheet = DailyDelivery::where('rider_id', $request->rider_id)
                    ->where('delivery_date', $request->date)
                    ->first();

        if (!$sheet) {
            return response()->json(null); // Return null if no record found
        }

        return response()->json($sheet);
    }

    public function getRiderHistory(Request $request, $riderId) {
         $history = DailyDelivery::where('rider_id', $riderId)
                     ->orderBy('delivery_date', 'desc')
                     ->get();
         return response()->json($history);
    }

    // 1. GET ALL PENDING SHEETS (For Admin List)
    public function getPendingSheets()
    {
        $sheets = DailyDelivery::where('status', 'pending')
                    ->with('rider:id,name,mobile') // Get Rider Name
                    ->orderBy('delivery_date', 'asc')
                    ->get();
                    
        return response()->json($sheets);
    }

    // 2. APPROVE SHEET (Deducts Money & Updates Status)
    public function approveSheet(Request $request)
    {
        $request->validate(['sheet_id' => 'required|exists:daily_deliveries,id']);

        return DB::transaction(function () use ($request) {
            $sheet = DailyDelivery::find($request->sheet_id);

            // Safety Check
            if ($sheet->status != 'pending') {
                return response()->json(['error' => 'Sheet already processed'], 400);
            }

            // 1. DEDUCT FROM RIDER WALLET
            // The "admin_commission" column holds (AdminFee + AdminSVC + RestComm)
            // This is the total amount the rider owes the system from that day's cash.
            $amountToDeduct = $sheet->admin_commission;

            $riderWallet = \App\Models\Wallet::where('user_id', $sheet->rider_id)->first();
            
            if (!$riderWallet) {
                // Should not happen, but auto-create if missing to avoid crash
                $riderWallet = \App\Models\Wallet::create(['user_id' => $sheet->rider_id]);
            }

            // Perform Deduction
            $riderWallet->decrement('balance', $amountToDeduct);
            
            // 2. UPDATE ADMIN WALLET (Earnings)
            // Assuming Admin ID 1 is the super admin/company
            $adminWallet = \App\Models\Wallet::where('user_id', 1)->first(); 
            if ($adminWallet) {
                $adminWallet->increment('earnings', $amountToDeduct);
            }

            // 3. RECORD TRANSACTION
            \App\Models\Transaction::create([
                'wallet_id' => $riderWallet->id,
                'admin_id' => 1, // System Admin
                'amount' => -$amountToDeduct, // Negative because it's a deduction
                'type' => 'deduction',
                'description' => "Daily Sheet Approval: " . $sheet->delivery_date->format('Y-m-d'),
                'balance_after' => $riderWallet->balance
            ]);

            // 4. MARK SHEET AS APPROVED
            $sheet->status = 'approved';
            $sheet->save();

            return response()->json(['message' => 'Sheet approved and wallet deducted successfully']);
        });
    }

    // 3. GET DAILY STATUS REPORT (Checks who submitted and who didn't)
    public function getDailyStatusReport(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        // A. Get all users who are riders
        $riders = \App\Models\User::where('role', 'rider')->get(['id', 'name']);

        // B. Get all sheets for the specific date
        $sheets = DailyDelivery::where('delivery_date', $request->date)
                    ->get()
                    ->keyBy('rider_id'); // Key by ID for easy lookup

        // C. Merge them to create the report
        $report = $riders->map(function($rider) use ($sheets) {
            $sheet = $sheets->get($rider->id);
            
            // Determine Status
            $status = 'missing';
            $sheetData = null;

            if ($sheet) {
                $status = $sheet->status; // 'pending' or 'approved'
                $sheetData = $sheet;
                // Append rider info to sheet data so SheetDetailScreen works
                $sheetData['rider'] = $rider; 
            }

            return [
                'rider_id' => $rider->id,
                'name' => $rider->name,
                'status' => $status, // missing, pending, approved
                'sheet_data' => $sheetData
            ];
        });

        return response()->json($report->values());
    }
}