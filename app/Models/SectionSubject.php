<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionSubject extends Model
{
    protected $table = 'section_subjects';

    protected $fillable = [
        'section_id',
        'subject_name',
        'teacher_name',
        'schedule',
        'ms_channel_id',
    ];
}
