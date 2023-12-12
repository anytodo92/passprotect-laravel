<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidMembership extends Model
{
    protected $table = 'paid_membership';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'status',
    ];
}
