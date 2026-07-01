<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TranscriptSetting extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'transcript_title',
        'registrar_name',
        'registrar_title',
        'certification_statement',
        'footer_statement',
        'grading_scale_note',
        'accreditation_note',
        'transcript_disclaimer',
        'show_recipient_info',
        'show_delivery_method',
        'show_purpose',
        'show_grade_points',
        'show_status',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'show_recipient_info' => 'boolean',
        'show_delivery_method' => 'boolean',
        'show_purpose' => 'boolean',
        'show_grade_points' => 'boolean',
        'show_status' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $transcriptSetting): void {
            if (! $transcriptSetting->is_active) {
                return;
            }

            self::query()
                ->where('institution_id', $transcriptSetting->institution_id)
                ->whereKeyNot($transcriptSetting->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
