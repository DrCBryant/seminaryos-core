<?php

namespace App\Core\Traits;

trait HasAuditFields
{
    protected static function bootHasAuditFields(): void
    {
        static::creating(function ($model) {
            if (property_exists($model, 'created_by') && !$model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }

            if (property_exists($model, 'updated_by') && !$model->updated_by && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (property_exists($model, 'updated_by') && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
