<?php

namespace App\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'type',
        'status',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'settings',
        'logo_path',
        'primary_color',
        'secondary_color',
        'max_users',
        'max_students',
        'max_storage_mb',
    ];

    protected $casts = [
        'settings' => 'array',
        'max_users' => 'integer',
        'max_students' => 'integer',
        'max_storage_mb' => 'integer',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function websites()
    {
        return $this->hasMany(Website::class);
    }

    public function websitePages()
    {
        return $this->hasMany(WebsitePage::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function coursePrograms()
    {
        return $this->hasMany(CourseProgram::class);
    }

    public function catalogs()
    {
        return $this->hasMany(Catalog::class);
    }

    public function academicTerms()
    {
        return $this->hasMany(AcademicTerm::class);
    }

    public function catalogPages()
    {
        return $this->hasMany(CatalogPage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
