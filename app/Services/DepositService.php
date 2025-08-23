<?php

namespace App\Services;

use App\Domain\Payments\{ProviderFactory, ProviderEvent};
use App\Support\Money;
use App\Models\{Transaction, LedgerEntry, Account, User};
use Illuminate\Support\Facades\DB;

class DepositService {
    public function __construct(private ProviderFactory $factory){}
    public function initiate(User $user, string $provider, string $amount){
        $money = new Money(currency: config('app.currency','KES'), amount:
        $amount);
        return $this->factory->for($provider)->initiateDeposit($user, $money);
    }
    public function recordSuccessfulDeposit(User $user, ProviderEvent $event):Transaction {
        return DB::transaction(function() use($user,$event){
            $tx = Transaction::create([
                'user_id'=>$user->id,
                'provider'=>$event->provider,
                'direction'=>'in',
                'status'=>'succeeded',
                'amount'=>$event->amount->amount,
                'currency'=>$event->amount->currency,
                'provider_ref'=>$event->reference,
                'meta'=>$event->meta,
            ]);
            $clearing = $user->accounts()->where('slug','clearing')->firstOrFail();
            $rules = $user->allocationRules()->where('active',true)->orderBy('priority')->get();
            $total = (float)$event->amount->amount;
            $sum = (float)$rules->sum('percent');
            if (abs($sum - 100.0) > 0.001) throw new \RuntimeException('Allocation must sum to 100%');
            LedgerEntry::create([
                'transaction_id'=>$tx->id,
                'account_id'=>$clearing->id,
                'entry_type'=>'debit',
                'amount'=>$total,
                'description'=>'Deposit received',
            ]);
            foreach ($rules as $rule) {
                $portion = round($total * ((float)$rule->percent/100), 2);
                if ($portion <= 0) continue;
                LedgerEntry::create([
                    'transaction_id'=>$tx->id,
                    'account_id'=>$rule->account_id,
                    'entry_type'=>'credit',
                    'amount'=>$portion,
                    'description'=>"Allocation {$rule->percent}%",
                ]);
            }
            return $tx;
        });
    }
}