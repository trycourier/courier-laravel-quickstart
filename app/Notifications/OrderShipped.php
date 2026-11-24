<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\CourierChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    // A failed send retries on the queue with the same idempotency key.
    public $tries = 5;

    public $backoff = [10, 60, 300];

    public function __construct(public Order $order)
    {
        // Queue the notification only once the transaction that shipped the
        // order commits, so a rolled-back shipment notifies nobody.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [CourierChannel::class];
    }

    /**
     * The Courier message, without `to`. CourierChannel adds the recipient.
     */
    public function toCourier(object $notifiable): array
    {
        return [
            'content' => [
                'title' => "Order #{$this->order->id} has shipped",
                // Courier fills in a link to this user's hosted preference page.
                'body' => "Your order is on its way.\n\nChoose what we send you: {{var \"urls.preferences\"}}",
            ],
            'routing' => [
                // `all` sends every channel listed. `single` would stop at the first one that works.
                'method' => 'all',
                // Skip SMS for a user with no phone number.
                'channels' => $notifiable->phone_number ? ['email', 'sms'] : ['email'],
            ],
            'data' => ['order_id' => $this->order->id],
        ];
    }
}
