<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'name',
        'domain',
        'primary_color',
        'secondary_color',
        'accent_color',
        'logo_path',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function pages()
    {
        return $this->hasMany(WebsitePage::class);
    }
}
