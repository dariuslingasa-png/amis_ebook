<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ebook extends Model
{
    protected $table = 'ebooks';

    protected $fillable = [
        'title',
        'description',
        'grade_level',   // plain text, e.g. "Grade 4"
        'file_path',
        'is_downloadable',
        'status',
        'created_by',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(EbookAccessLog::class, 'ebook_id');
    }
}
