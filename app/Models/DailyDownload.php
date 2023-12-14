<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyDownload extends Model
{
    use HasFactory;

    protected $table = 'daily_downloads';

    protected $fillable = [
        'user_id',
        'link_id',
        'passdrop_url',
        'downloads',
        'download_date',
    ];

    protected $hidden = [
        'id'
    ];
}
