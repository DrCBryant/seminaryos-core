<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogPage extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'catalog_id',
        'source_type',
        'source_id',
        'title',
        'slug',
        'page_type',
        'rendered_content',
        'status',
        'is_public',
        'seo_title',
        'seo_description',
        'last_generated_at',
        'published_at',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'is_public' => 'boolean',
        'last_generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function catalog()
    {
        return $this->belongsTo(Catalog::class);
    }
}
