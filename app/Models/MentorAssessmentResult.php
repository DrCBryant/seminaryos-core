<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorAssessmentResult extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'attempt_id';

    protected $fillable = ['mentor_faculty_id', 'decided_by_user_id', 'decided_at', 'status', 'feedback'];

    protected $casts = ['decided_at' => 'datetime'];
}
