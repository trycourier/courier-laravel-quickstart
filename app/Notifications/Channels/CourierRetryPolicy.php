<?php

namespace App\Notifications\Channels;

use Courier\Core\Exceptions\APIConnectionException;
use Courier\Core\Exceptions\ConflictException;
use Courier\Core\Exceptions\RateLimitException;
use Throwable;

/**
 * Queue middleware for notifications sent through CourierChannel.
 *
 * With the same idempotency key, Courier runs a send again only after a
 * connection error, a timeout, or a rate limit, once the key's 60-second lock
 * clears. A retry inside that lock gets a 409, which is worth retrying too.
 * Courier stores any other error for 25 hours and replays it, so this fails
 * the job at once instead.
 */
class CourierRetryPolicy
{
    public function handle(object $job, callable $next): mixed
    {
        try {
            return $next($job);
        } catch (APIConnectionException|RateLimitException|ConflictException $e) {
            // The queue retries with the same key, after $backoff.
            throw $e;
        } catch (Throwable $e) {
            // No retries. The job goes to failed_jobs with this error.
            $job->fail($e);

            throw $e;
        }
    }
}
