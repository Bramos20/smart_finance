<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundupSetting extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'enabled' => 'boolean',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function savingsAccount()
    {
        return $this->belongsTo(Account::class, 'savings_account_id');
    }
    
    public function calculateRoundup(float $amount): float
    {
        if (!$this->enabled) return 0;
        
        $roundTo = (int) $this->round_to;
        $rounded = ceil($amount / $roundTo) * $roundTo;
        
        return round($rounded - $amount, 2);
    }
}
