<?php

namespace App\Notifications\Channels;

use Courier\Client;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CourierChannel
{
    /**
     * Send the notification through Courier and return Courier's request id.
     */
    public function send(object $notifiable, Notification $notification): string
    {
        $apiKey = config('services.courier.key')
            ?: throw new RuntimeException('Set COURIER_API_KEY in .env.');

        $courier = new Client(apiKey: $apiKey);

        $response = $courier->send->message(
            message: [
                // Who the user is to Courier: User::routeNotificationForCourier().
                'to' => $notifiable->routeNotificationFor('courier', $notification),
                ...$notification->toCourier($notifiable),
            ],
            // The notification's UUID survives queue retries, so a retry can't send twice.
            idempotencyKey: $notification->id,
        );

        Log::info("Courier accepted notification {$notification->id} as {$response->requestID}");

        return $response->requestID;
    }
}
