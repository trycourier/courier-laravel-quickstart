# Courier notifications: Laravel quickstart

A Laravel 13 app that notifies a user by email and SMS when an order ships, through Courier. Courier plugs into Laravel Notifications as a custom channel: `OrderShipped` returns `CourierChannel` from `via()`, so `$user->notify(new OrderShipped($order))` works the way every Laravel notification does. The notification is queued and waits for the database transaction to commit, and its id doubles as Courier's idempotency key, so a queue retry never sends twice.

This is the companion project for [Add notifications to a Laravel app](https://www.courier.com/docs/guides/add-notifications-to-laravel) in the Courier docs.

## Build this with a coding agent

Paste this into Claude Code, Cursor, or Codex from the root of your Laravel app:

```text
Add email and SMS notifications to this Laravel app with Courier.
Follow https://www.courier.com/docs/guides/add-notifications-to-laravel.md and the reference app at https://github.com/trycourier/courier-laravel-quickstart.
Use the trycourier/courier Composer package and read COURIER_API_KEY through config/services.php.
Add a custom notification channel that calls Courier, queue the notification with ShouldQueue and afterCommit, and pass the notification id as the idempotency key.
Send email and SMS in one send, and skip SMS when the user has no phone number.
When done, run php artisan courier:send and show me the request id.
```

Install the Courier skill with `npx skills add trycourier/courier-skills` and connect the [Courier MCP server](https://www.courier.com/docs/resources/mcp), so the agent can read Courier Logs and templates itself. `AGENTS.md` lists the commands and the rules to keep.

## Prerequisites

- PHP 8.3 or newer and Composer
- A [Courier account](https://app.courier.com/signup) and an [API key](https://app.courier.com/settings/api-keys). A Test key is fine.
- For SMS: an SMS provider such as Twilio connected in [Integrations](https://app.courier.com/integrations/catalog), and your phone number in E.164 form

Email works on day one. In a Test environment, the Courier Test Email Provider delivers to the teammates in your workspace, so set `DEMO_EMAIL` to the address you log in to Courier with.

## Run it

```bash
git clone https://github.com/trycourier/courier-laravel-quickstart.git
cd courier-laravel-quickstart
composer install
cp .env.example .env      # add your API key and DEMO_EMAIL
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan courier:send
```

The command ships a demo order for `user_123` and prints Courier's request id. The email arrives at `DEMO_EMAIL`, and the text at `DEMO_PHONE` if you set one. Without a phone number, the notification routes to email only. Every message ends with a link to the user's hosted preference page.

To send one message without the app, run `first_send.php`:

```bash
COURIER_API_KEY=YOUR_COURIER_API_KEY DEMO_EMAIL=YOUR_EMAIL php first_send.php
```

## How the pieces fit

| File | What it does |
|---|---|
| `app/Models/Order.php` | `markShipped()` updates the order and calls `$this->user->notify(new OrderShipped($this))` in one transaction |
| `app/Notifications/OrderShipped.php` | `ShouldQueue` with `afterCommit()`. `via()` returns `CourierChannel`, and `toCourier()` builds the content and routing |
| `app/Notifications/Channels/CourierChannel.php` | One `$client->send->message` call, with the notification id as the idempotency key |
| `app/Notifications/Channels/CourierRetryPolicy.php` | Queue middleware: retries connection errors, rate limits, and 409s, and fails the job on any other error, which Courier would only replay |
| `app/Models/User.php` | `routeNotificationForCourier()` maps the user to a Courier recipient: `user_<id>`, email, and phone |
| `config/services.php` | Reads `COURIER_API_KEY`, `DEMO_EMAIL`, and `DEMO_PHONE` |
| `routes/console.php` | `php artisan courier:send`, which ships a demo order end to end |

```
$order->markShipped()
  └─ DB::transaction ─> $user->notify(new OrderShipped($order))
                          └─ commit ─> queued job ─> CourierChannel ─> POST /send ─> Courier ─┬─ email (your email provider)
                                                                                               └─ sms   (your SMS provider)
```

## Adapting it to your app

**Run a queue worker.** `.env.example` sets `QUEUE_CONNECTION=database`, so `notify()` writes a job and `php artisan queue:work` sends it. The demo command switches to the `sync` queue so it can print the request id. On `sync`, nothing retries, and a failed send throws from `markShipped()` after the order has committed as shipped.

**Read the real user.** `courier:send` pins `user_123` so the sample runs without auth. In your app, notify the user from `auth()->user()` or the database.

**Use a template.** `toCourier()` sends inline `content` so the sample runs against a fresh workspace. In production, design the message in Courier and return `'template' => 'YOUR_TEMPLATE_ID'` instead, so copy changes don't need a deploy.

## Troubleshooting

**`Set COURIER_API_KEY in .env.`** Copy `.env.example` to `.env` and fill it in. If you cached config, run `php artisan config:clear`, and `php artisan queue:restart` if a worker is running.

**The email never arrives.** In Test, `DEMO_EMAIL` has to belong to a teammate in your Courier workspace. To reach anyone else, connect your own email provider.

**The SMS shows `UNROUTABLE` in Logs.** No SMS provider is connected in this environment, or `DEMO_PHONE` isn't E.164 (`+12025550156`, not `2025550156`).

**Nothing sends in your own app.** The notification is queued. Run `php artisan queue:work`.

## License

MIT
