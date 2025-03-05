<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IaucMarketPriceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'status',
        'error_message'
    ];
}
