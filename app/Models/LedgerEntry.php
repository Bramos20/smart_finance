<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $guarded = [];
    public function transaction(){ return $this->belongsTo(Transaction::class); }
    public function account(){ return $this->belongsTo(Account::class); }
}
