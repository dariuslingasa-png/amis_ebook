<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = [
        'name',
        'code',
        'grade_level',
        'school_year',
    ];

    public function ebooks()
    {
        return $this->hasMany(Ebook::class);
    }
}
