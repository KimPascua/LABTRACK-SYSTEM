<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteActivity extends Model
{
    protected $fillable = [
        'pc_name',
        'student_id',
        'student_name',
        'lab',
        'website_domain',
        'action',
        'status',
        'reason',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];
}
