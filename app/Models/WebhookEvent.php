<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'signature',
        'headers',
        'payload',
        'status',
        'processed_at',
    ];
}
