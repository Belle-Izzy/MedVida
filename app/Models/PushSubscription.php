<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = [
        'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'content_encoding',
        'owner_type', 'owner_id', 'access_token_id',
    ];

    protected function casts(): array
    {
        return ['endpoint' => 'encrypted', 'p256dh' => 'encrypted', 'auth' => 'encrypted'];
    }
}
