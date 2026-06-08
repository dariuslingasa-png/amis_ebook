<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbookAccessLog extends Model
{
    protected $table = 'ebook_access_logs';

    public $timestamps = false;

    protected $fillable = [
        'ebook_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ebook()
    {
        return $this->belongsTo(Ebook::class, 'ebook_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
