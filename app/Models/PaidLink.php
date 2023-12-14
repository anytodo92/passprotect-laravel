<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidLink extends Model
{
    use HasFactory;

    protected $table = 'paid_links';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'link_id',
        'type',
        'amount',
        'status',
    ];
}
