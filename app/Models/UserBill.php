<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserBill extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'next_due_date' => 'datetime',
        'last_paid_at' => 'datetime',
        'meta' => 'array',
        'auto_pay' => 'boolean',
        'active' => 'boolean',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }
    
    public function calculateNextDueDate(?Carbon $from = null): Carbon
    {
        $from = $from ?? now();
        
        return match($this->frequency) {
            'weekly' => $from->next($this->due_day), // due_day is 1=Monday, 7=Sunday
            'monthly' => $from->day($this->due_day)->addMonth(),
            'quarterly' => $from->day($this->due_day)->addMonths(3),
            'yearly' => $from->day($this->due_day)->addYear(),
        };
    }
    
    public function updateNextDueDate(): void
    {
        $this->next_due_date = $this->calculateNextDueDate();
        $this->save();
    }
    
    public function isDue(): bool
    {
        return $this->next_due_date && $this->next_due_date->isPast();
    }
}
