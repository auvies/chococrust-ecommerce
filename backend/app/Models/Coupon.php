<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'type', 'value', 'min_order_amount', 'max_discount_amount', 'usage_limit',
    'usage_limit_per_customer', 'starts_at', 'expires_at', 'is_active',
])]
class Coupon extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(CouponScope::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** True when this coupon has no scoping rows, i.e. applies store-wide. */
    public function isStoreWide(): bool
    {
        return $this->scopes()->doesntExist();
    }
}
