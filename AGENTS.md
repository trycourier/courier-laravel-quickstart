# Courier Laravel quickstart

A Laravel 13 app that notifies a user by email and SMS through Courier when an order ships, from a custom Laravel notification channel. Guide: https://www.courier.com/docs/guides/add-notifications-to-laravel

## Commands

- Install: `composer install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate`
- Send: `php artisan courier:send` ships a demo order for `user_123` and prints the Courier request id
- First send only: `COURIER_API_KEY=... DEMO_EMAIL=... php first_send.php`
- Check: `php artisan about` boots the app without calling Courier
- Queue: `php artisan queue:work` sends queued notifications in a real app

## Environment (`.env`, read through `config/services.php`)

- `COURIER_API_KEY`: `config('services.courier.key')`, read in `app/Notifications/Channels/CourierChannel.php`
- `DEMO_EMAIL`, `DEMO_PHONE`: read only by `courier:send` in `routes/console.php`

## Files that matter

- `app/Notifications/Channels/CourierChannel.php`: the one `$client->send->message` call
- `app/Notifications/Channels/CourierRetryPolicy.php`: queue middleware that retries only connection errors and rate limits
- `app/Notifications/OrderShipped.php`: `via()` returns `CourierChannel`, `toCourier()` builds the message, queued after commit
- `app/Models/User.php`: `routeNotificationForCourier()` maps a user to a Courier recipient (`user_<id>`, email, phone)
- `app/Models/Order.php`: `markShipped()` updates the order and notifies in one transaction
- `routes/console.php`: `courier:send`, the demo

## Rules

- Use `trycourier/courier`. Message array keys are camelCase (`userID`, `phoneNumber`). The SDK sends them as `user_id` and `phone_number`.
- The API key is server-only. Read it through `config()`, never `env()` outside `config/`.
- Keep `ShouldQueue` and `$this->afterCommit()` on notifications, so a rolled-back transaction never notifies.
- The idempotency key is the notification's `id`, a UUID per `notify()` call that queue retries reuse. Don't replace it with a timestamp.
- Keep `CourierRetryPolicy` in `middleware()`. Courier replays any other error for the same key for 25 hours, so only connection errors and rate limits retry.
- Route with `all` over `email` and `sms`, and drop `sms` when the user has no `phone_number`.
- Don't call Twilio or a mail provider directly. Courier routes every channel.
