<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileListUser extends Model
{
    use HasFactory;

    /**
     *
     * The table is associated with Model
     *
     * @var string
     *
     */

    protected $table = 'file_list_user';
    protected $primaryKey = 'id';

    protected $fillable = [
        'dropbox_url',
        'passdrop_url',
        'passdrop_pwd',
        'user_id',
        'is_verified',
        'alt_email',
        'download_count',
        'expires_on',
        'link_type',
        'track_ip',
        'expire_count',
        'is_paid',
        'paypop_title',
        'paypop_sub',
        'is_expiry_extended',
        'created_on',
        'last_download',
        'service',
        'filename'
    ];

}
