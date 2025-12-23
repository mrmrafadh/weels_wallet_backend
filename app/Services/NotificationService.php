<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        // Point to your JSON file from Firebase
        $factory = (new Factory)->withServiceAccount(base_path('firebase_credentials.json'));
        $this->messaging = $factory->createMessaging();
    }

    public function send($fcmToken, $title, $body)
    {
        if (!$fcmToken) return;

        $message = CloudMessage::withTarget('token', $fcmToken)
            ->withNotification(ERROR_Notification::create($title, $body));
            // Note: The library syntax changes slightly by version, 
            // but conceptually you send 'notification' => ['title' => ..., 'body' => ...]

        try {
            $this->messaging->send($message);
        } catch (\Exception $e) {
            // Log error or ignore if token is invalid
        }
    }
}