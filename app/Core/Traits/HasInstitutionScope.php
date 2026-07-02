<?php

namespace App\Core\Traits;

use App\Core\Scopes\InstitutionScope;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Model;

trait HasInstitutionScope
{
    protected static function bootHasInstitutionScope(): void
    {
        static::addGlobalScope(new InstitutionScope);

        static::creating(function (Model $model) {
            if (! $model->institution_id && auth()->check()) {
                $model->institution_id = auth()->user()->currentInstitution?->id;
            }
        });
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
