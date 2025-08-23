<?php

namespace App\Services;

use App\Models\{Account, LedgerEntry, User};

class BalanceService {
    public function accountBalance(Account $account): float {
        $credits = (float) LedgerEntry::where('account_id',$account->id)->where('entry_type','credit')->sum('amount');
        $debits = (float) LedgerEntry::where('account_id',$account->id)->where('entry_type','debit')->sum('amount');
        return round($credits - $debits, 2);
    }
    public function userBalances(User $user): array {
        return $user->accounts()->get()->mapWithKeys(fn($a)=> [ $a->slug =>$this->accountBalance($a) ])->toArray();
    }
}