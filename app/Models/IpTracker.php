<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpTracker extends Model
{
    use HasFactory;

    protected $table = 'ip_tracker';
    protected $primaryKey = 'id';

    protected $fillable = [
        'link_id',
        'ip',
        'city',
        'country',
        'reserved',
        'latlong'
    ];
}
