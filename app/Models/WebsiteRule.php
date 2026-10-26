<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteRule extends Model
{
    protected $fillable = [
        'website_domain',
        'scope',
        'lab',
        'restriction',
        'status',
        'reason',
    ];
}
