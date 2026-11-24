<?php

namespace App\Notifications\Channels;

use Courier\Core\Exceptions\APIConnectionException;
use Courier\Core\Exceptions\RateLimitException;
use Throwable;

/**
 * Queue middleware for notifications sent through CourierChannel.
 *
 * With the same idempotency key, Courier runs a send again only after a
 * connection error, a timeout, or a rate limit. It stores any other error for 25 hours
 * and replays it on a retry, so this fails the job at once instead.
 */
class CourierRetryPolicy
{
    public function handle(object $job, callable $next): mixed
    {
        try {
            return $next($job);
        } catch (APIConnectionException|RateLimitException $e) {
            // The queue retries with the same key.
            throw $e;
        } catch (Throwable $e) {
            // No retries. The job goes to failed_jobs with this error.
            $job->fail($e);

            throw $e;
        }
    }
}
