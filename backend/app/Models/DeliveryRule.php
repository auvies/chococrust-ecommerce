<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'scope', 'category_id', 'product_id', 'delivery_type', 'is_deliverable',
    'min_order_amount', 'flat_fee', 'local_areas', 'estimated_minutes', 'priority', 'is_active',
])]
class DeliveryRule extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_deliverable' => 'boolean',
            'is_active' => 'boolean',
            'min_order_amount' => 'decimal:2',
            'flat_fee' => 'decimal:2',
            'local_areas' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Structural invariant the DB schema can't portably enforce itself
        // (see the migration's comment): scope decides which target column
        // must be set.
        static::saving(function (DeliveryRule $rule) {
            $hasCategory = $rule->category_id !== null;
            $hasProduct = $rule->product_id !== null;

            $valid = match ($rule->scope) {
                'global' => ! $hasCategory && ! $hasProduct,
                'category' => $hasCategory && ! $hasProduct,
                'product' => $hasProduct && ! $hasCategory,
                default => false,
            };

            if (! $valid) {
                throw new InvalidArgumentException(
                    "DeliveryRule scope '{$rule->scope}' does not match its category_id/product_id."
                );
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
