<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    protected $table = 'grade_levels';

    protected $fillable = [
        'name',
        'sort_order',
        'capacity',
        'enrolled_count',
        'is_active',
        'school_year',
    ];

    public function ebooks()
    {
        return $this->hasMany(Ebook::class);
    }
}
