<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Catalog extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'title',
        'slug',
        'academic_year',
        'status',
        'effective_start_date',
        'effective_end_date',
        'is_active',
        'description',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected $casts = [
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function pages()
    {
        return $this->hasMany(CatalogPage::class);
    }
}
