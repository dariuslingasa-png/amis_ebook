<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ebook extends Model
{
    protected $table = 'ebooks';

    protected $fillable = [
        'title',
        'description',
        'grade_level',   // plain text, e.g. "Grade 4"
        'file_path',
        'cover_image_path',
        'is_downloadable',
        'status',
        'created_by',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
    ];

    /**
     * Get the public URL for the cover image.
     */
    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image_path);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(EbookAccessLog::class, 'ebook_id');
    }
}
