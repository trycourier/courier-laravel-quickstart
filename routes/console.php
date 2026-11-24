<?php

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

Artisan::command('courier:send', function () {
    // Check the config before touching any user.
    $key = config('services.courier.key');
    if (! $key || $key === 'YOUR_COURIER_API_KEY') {
        $this->error('Set COURIER_API_KEY in .env.');

        return 1;
    }

    $email = config('services.courier.demo_email');
    if (! $email || $email === 'YOUR_EMAIL') {
        $this->error('Set DEMO_EMAIL in .env to the email you log in to Courier with.');

        return 1;
    }

    // Run the queued notification in this process, so the command waits for
    // Courier's answer. A failed send throws here instead of retrying.
    config(['queue.default' => 'sync']);
    Event::listen(NotificationSent::class, function (NotificationSent $event) {
        $this->info("Courier request id: {$event->response}");
    });

    // The demo user is user_123. A real app gets the user from the session
    // (auth()->user()) or the database.
    $user = User::findOrNew(123);
    $user->forceFill([
        'id' => 123,
        'name' => 'Sarah Bennett',
        'email' => $email,
        'phone_number' => config('services.courier.demo_phone') ?: null,
        'password' => $user->password ?? Str::random(40),
    ])->save();

    $order = $user->orders()->create();
    $order->markShipped();

    $this->line("Shipped order #{$order->id} for user_123. See it in https://app.courier.com/logs");
})->purpose('Ship a demo order, which notifies user_123 by email and SMS');
