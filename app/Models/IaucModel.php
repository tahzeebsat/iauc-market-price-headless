<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IaucModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'iauc_make_id',
        'name',
        'sat_name'
    ];

}
