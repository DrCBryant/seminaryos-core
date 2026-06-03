<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsitePage extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'website_id',
        'title',
        'slug',
        'page_type',
        'content',
        'status',
        'is_public',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected $casts = [
        'content' => 'array',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
