<?php

namespace App\Models;

use App\Notifications\OrderShipped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = ['status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the order shipped and notify its user once the change commits.
     */
    public function markShipped(): void
    {
        DB::transaction(function () {
            // Start from the stored row, so a retry after a rollback writes the status again.
            $this->refresh();
            $this->update(['status' => 'shipped']);

            // Each call gets a new notification id, so shipping again later notifies again.
            $this->user->notify(new OrderShipped($this));
        });
    }
}
