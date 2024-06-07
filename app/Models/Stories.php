<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stories extends Model
{
    use HasFactory;
    protected $table = 'stories';
    protected $fillable = [
        'image',
        'time',
        'viewer',
        'privacy',
        'id_client',
    ];

    const public = 1;
    const friend = 2;
    const private = 4;

}
