<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zcta extends Model
{
    protected $primaryKey = 'zcta5';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['zcta5', 'source_vintage'];
}
