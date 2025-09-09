<?php

namespace App\Services;

use App\Models\{User, RoundupSetting, Transaction, LedgerEntry};
use Illuminate\Support\Facades\{DB, Log};

class RoundupService
{
    public function processRoundup(Transaction $transaction): ?LedgerEntry
    {
        // Only process round-ups for outgoing transactions (bill payments, etc.)
        if ($transaction->direction !== 'out' || $transaction->status !== 'succeeded') {
            return null;
        }
        
        $user = $transaction->user;
        $roundupSetting = $user->roundupSetting;
        
        if (!$roundupSetting || !$roundupSetting->enabled) {
            return null;
        }
        
        $amount = (float) $transaction->amount;
        $roundupAmount = $roundupSetting->calculateRoundup($amount);
        
        if ($roundupAmount <= 0) {
            return null; // No round-up needed
        }
        
        return DB::transaction(function () use ($user, $roundupSetting, $transaction, $roundupAmount) {
            // Check monthly limit if set
            if ($roundupSetting->monthly_limit) {
                $monthlyRoundups = $this->getMonthlyRoundupTotal($user);
                if (($monthlyRoundups + $roundupAmount) > $roundupSetting->monthly_limit) {
                    Log::info('Round-up skipped due to monthly limit', [
                        'user_id' => $user->id,
                        'monthly_total' => $monthlyRoundups,
                        'limit' => $roundupSetting->monthly_limit,
                        'attempted_roundup' => $roundupAmount
                    ]);
                    return null;
                }
            }
            
            // Get accounts
            $mainAccount = $user->accounts()->where('slug', 'main')->firstOrFail();
            $savingsAccount = $roundupSetting->savingsAccount;
            
            // Create round-up transaction
            $roundupTx = Transaction::create([
                'user_id' => $user->id,
                'provider' => 'system',
                'direction' => 'internal',
                'status' => 'succeeded',
                'amount' => $roundupAmount,
                'currency' => $transaction->currency,
                'meta' => [
                    'type' => 'roundup',
                    'original_transaction_id' => $transaction->id,
                    'original_amount' => $transaction->amount,
                    'rounded_to' => $roundupSetting->round_to
                ]
            ]);
            
            // Debit Main account
            LedgerEntry::create([
                'transaction_id' => $roundupTx->id,
                'account_id' => $mainAccount->id,
                'entry_type' => 'debit',
                'amount' => $roundupAmount,
                'description' => "Round-up savings from {$transaction->amount}"
            ]);
            
            // Credit Savings account
            $savingsEntry = LedgerEntry::create([
                'transaction_id' => $roundupTx->id,
                'account_id' => $savingsAccount->id,
                'entry_type' => 'credit',
                'amount' => $roundupAmount,
                'description' => "Round-up savings from {$transaction->amount}"
            ]);
            
            Log::info('Round-up processed', [
                'user_id' => $user->id,
                'original_amount' => $transaction->amount,
                'roundup_amount' => $roundupAmount,
                'transaction_id' => $roundupTx->id
            ]);
            
            return $savingsEntry;
        });
    }
    
    public function createOrUpdateSettings(User $user, array $data): RoundupSetting
    {
        return $user->roundupSetting()->updateOrCreate([], [
            'enabled' => $data['enabled'] ?? false,
            'round_to' => $data['round_to'] ?? '10',
            'savings_account_id' => $data['savings_account_id'],
            'monthly_limit' => $data['monthly_limit'] ?? null
        ]);
    }
    
    public function getMonthlyRoundupTotal(User $user): float
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        return (float) $user->transactions()
            ->where('direction', 'internal')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereJsonContains('meta->type', 'roundup')
            ->sum('amount');
    }
    
    public function getRoundupStats(User $user): array
    {
        $thisMonth = $this->getMonthlyRoundupTotal($user);
        
        $allTime = (float) $user->transactions()
            ->where('direction', 'internal')
            ->whereJsonContains('meta->type', 'roundup')
            ->sum('amount');
            
        $thisYear = (float) $user->transactions()
            ->where('direction', 'internal')
            ->whereYear('created_at', now()->year)
            ->whereJsonContains('meta->type', 'roundup')
            ->sum('amount');
            
        return [
            'this_month' => $thisMonth,
            'this_year' => $thisYear,
            'all_time' => $allTime,
            'average_per_transaction' => $allTime > 0 ? round($allTime / max(1, $user->transactions()->whereJsonContains('meta->type', 'roundup')->count()), 2) : 0
        ];
    }
}