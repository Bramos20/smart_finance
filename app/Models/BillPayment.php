<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'provider_response' => 'array',
    ];
    
    public function userBill()
    {
        return $this->belongsTo(UserBill::class);
    }
    
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
