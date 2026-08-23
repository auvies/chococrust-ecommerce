<?php

namespace App\Models;

use Database\Factories\CodRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'payment_id', 'status', 'amount_due', 'amount_collected',
    'collected_by', 'collected_at', 'verified_by', 'verified_at', 'notes',
])]
class CodRecord extends Model
{
    /** @use HasFactory<CodRecordFactory> */
    use HasFactory;

    public $table = 'cod_records';

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'collected_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
