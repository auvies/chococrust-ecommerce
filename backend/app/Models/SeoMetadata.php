<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'seoable_type', 'seoable_id', 'meta_title', 'meta_description', 'meta_keywords',
    'canonical_url', 'og_title', 'og_description', 'og_image_url',
])]
class SeoMetadata extends Model
{
    public $table = 'seo_metadata';

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
