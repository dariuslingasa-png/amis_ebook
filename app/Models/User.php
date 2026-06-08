<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'role', 'access_permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'access_permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'access_permissions' => 'array',
        ];
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    /**
     * Get the subjects taught by the teacher.
     */
    public function taughtSubjects()
    {
        if ($this->role !== 'teacher') {
            return collect();
        }

        // Get the distinct subject names taught by this teacher from section_subjects
        $subjectNames = SectionSubject::where('teacher_name', $this->name)
            ->whereNotNull('subject_name')
            ->pluck('subject_name')
            ->unique();

        return Subject::whereIn('name', $subjectNames)->get();
    }
}
