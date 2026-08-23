<?php

namespace App\Models;

use Database\Factories\ThemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'config', 'is_active'])]
class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one theme may be active at a time. Enforced here rather than
        // a DB partial-unique-index for portability across drivers.
        static::saved(function (Theme $theme) {
            if ($theme->is_active) {
                static::where('id', '!=', $theme->id)->update(['is_active' => false]);
            }
        });
    }
}
