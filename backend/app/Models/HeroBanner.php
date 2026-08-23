<?php

namespace App\Models;

use Database\Factories\HeroBannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'subtitle', 'image_url', 'mobile_image_url', 'alt_text',
    'link_url', 'cta_text', 'sort_order', 'is_active', 'starts_at', 'ends_at',
])]
class HeroBanner extends Model
{
    /** @use HasFactory<HeroBannerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
