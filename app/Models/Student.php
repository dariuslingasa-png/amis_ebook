<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'user_id',
        'student_number',
        'school_email',
        'grade_level',
        'school_year',
        'section',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
