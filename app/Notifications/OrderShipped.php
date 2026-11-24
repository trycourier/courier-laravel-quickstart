<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\CourierChannel;
use App\Notifications\Channels\CourierRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    // CourierRetryPolicy fails the job at once on any error Courier would replay.
    public $tries = 5;

    // Courier holds the key for 60 seconds, so the first retry waits that long.
    public $backoff = [60, 120, 300];

    public function __construct(public Order $order)
    {
        // Queue only after the shipping transaction commits, so a rollback notifies nobody.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [CourierChannel::class];
    }

    public function middleware(object $notifiable, string $channel): array
    {
        return [new CourierRetryPolicy];
    }

    /**
     * The Courier message, without `to`. CourierChannel adds the recipient.
     */
    public function toCourier(object $notifiable): array
    {
        return [
            'content' => [
                'title' => "Order #{$this->order->id} has shipped",
                // Courier links this user's hosted preference page, because the send has a user_id.
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
