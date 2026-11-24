<?php

namespace App\Notifications\Channels;

use Courier\Core\Exceptions\APIConnectionException;
use Courier\Core\Exceptions\ConflictException;
use Courier\Core\Exceptions\RateLimitException;
use Throwable;

/**
 * Queue middleware: fail at once on any error Courier would replay for the same key.
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
