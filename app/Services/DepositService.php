<?php

namespace App\Services;

use App\Domain\Payments\{ProviderFactory, ProviderEvent};
use App\Support\Money;
use App\Models\{Transaction, LedgerEntry, Account, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositService {
    public function __construct(private ProviderFactory $factory){}
    
    public function initiate(User $user, string $provider, string $amount){
        $money = new Money(currency: config('app.currency','KES'), amount: $amount);
        return $this->factory->for($provider)->initiateDeposit($user, $money);
    }
    
    public function recordSuccessfulDeposit(User $user, ProviderEvent $event): Transaction {
        return DB::transaction(function() use($user, $event){
            Log::info('Recording successful deposit', [
                'user_id' => $user->id,
                'provider' => $event->provider,
                'amount' => $event->amount->amount,
                'reference' => $event->reference
            ]);

            $tx = Transaction::create([
                'user_id' => $user->id,
                'provider' => $event->provider,
                'direction' => 'in',
                'status' => 'succeeded',
                'amount' => $event->amount->amount,
                'currency' => $event->amount->currency,
                'provider_ref' => $event->reference,
                'meta' => $event->meta,
            ]);

            // Get required accounts
            $clearing = $user->accounts()->where('slug','clearing')->firstOrFail();
            $systemRevenue = $user->accounts()->where('slug','system_revenue')->firstOrFail();
            $rules = $user->allocationRules()->where('active',true)->orderBy('priority')->get();
            
            $total = (float)$event->amount->amount;
            $fee = (float) config('app.service_fee_flat', 2);
            $netTotal = $total - $fee;

            Log::info('Deposit allocation details', [
                'total' => $total,
                'fee' => $fee,
                'net_total' => $netTotal,
                'rules_count' => $rules->count()
            ]);

            // Verify allocation rules sum to 100%
            $sum = (float)$rules->sum('percent');
            if (abs($sum - 100.0) > 0.001) {
                throw new \RuntimeException("Allocation must sum to 100% for User ID: {$user->id}. Sum: {$sum}");
            }

            // 1. Credit clearing account with full deposit amount
            LedgerEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $clearing->id,
                'entry_type' => 'credit',
                'amount' => $total,
                'description' => 'Deposit received',
            ]);

            // 2. Allocate net amount to user buckets
            foreach ($rules as $rule) {
                $portion = round($netTotal * ((float)$rule->percent/100), 2);
                if ($portion <= 0) continue;
                
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $rule->account_id,
                    'entry_type' => 'credit',
                    'amount' => $portion,
                    'description' => "Allocation {$rule->percent}%",
                ]);

                // Debit clearing for this portion
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $clearing->id,
                    'entry_type' => 'debit',
                    'amount' => $portion,
                    'description' => "Allocation to {$rule->account->name}",
                ]);
            }

            // 3. Book service fee
            if ($fee > 0) {
                // Credit system revenue
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $systemRevenue->id,
                    'entry_type' => 'credit',
                    'amount' => $fee,
                    'description' => 'Service fee',
                ]);

                // Debit clearing for fee
                LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $clearing->id,
                    'entry_type' => 'debit',
                    'amount' => $fee,
                    'description' => 'Service fee deducted',
                ]);
            }

            Log::info('Deposit recorded successfully', [
                'transaction_id' => $tx->id,
                'ledger_entries' => $tx->ledger()->count()
            ]);

            return $tx;
        });
    }
}