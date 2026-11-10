<?php

namespace App\Models\Antrol;

use Illuminate\Database\Eloquent\Model;

class WsrsToken extends Model
{
    protected $table = 'antrol_wsrs_tokens';

    protected $fillable = ['username', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
