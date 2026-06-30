<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialTranscript extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'transcript_number',
        'status',
        'purpose',
        'requested_at',
        'issued_at',
        'recipient_name',
        'recipient_email',
        'delivery_method',
        'registrar_notes',
        'internal_notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OfficialTranscriptLine::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
