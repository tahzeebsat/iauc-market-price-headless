<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IaucMake extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sat_name'
    ];
}
