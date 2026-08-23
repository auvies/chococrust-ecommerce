<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_id', 'status', 'location', 'note', 'occurred_at'])]
class DeliveryTrackingEvent extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
