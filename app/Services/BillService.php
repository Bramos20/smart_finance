<?php

namespace App\Services;

use App\Models\{User, UserBill, BillPayment, Transaction, LedgerEntry, Account};
use App\Domain\Payments\ProviderFactory;
use App\Support\Money;
use Illuminate\Support\Facades\{DB, Log};
use Carbon\Carbon;

class BillService
{
    public function __construct(
        private ProviderFactory $providerFactory,
        private BalanceService $balanceService
    ) {}
    
    public function createBill(User $user, array $data): UserBill
    {
        $bill = $user->bills()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? 'utilities',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'KES',
            'frequency' => $data['frequency'] ?? 'monthly',
            'due_day' => $data['due_day'] ?? 1,
            'merchant_code' => $data['merchant_code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'auto_pay' => $data['auto_pay'] ?? false,
            'meta' => $data['meta'] ?? [],
        ]);
        
        $bill->updateNextDueDate();
        
        return $bill;
    }
    
    public function processDueBills(): int
    {
        $processed = 0;
        
        // Get all bills that are due and have auto_pay enabled
        $dueBills = UserBill::where('auto_pay', true)
            ->where('active', true)
            ->where('next_due_date', '<=', now())
            ->with(['user', 'user.accounts'])
            ->get();
            
        Log::info('Processing due bills', ['count' => $dueBills->count()]);
        
        foreach ($dueBills as $bill) {
            try {
                $result = $this->payBill($bill);
                if ($result) {
                    $processed++;
                    Log::info('Bill paid successfully', [
                        'bill_id' => $bill->id,
                        'user_id' => $bill->user_id,
                        'amount' => $bill->amount
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to pay bill', [
                    'bill_id' => $bill->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $processed;
    }
    
    public function payBill(UserBill $bill, bool $manualPayment = false): ?BillPayment
    {
        return DB::transaction(function () use ($bill, $manualPayment) {
            $user = $bill->user;
            
            // Get Bills account balance
            $billsAccount = $user->accounts()->where('slug', 'bills')->firstOrFail();
            $mainAccount = $user->accounts()->where('slug', 'main')->firstOrFail();
            
            $billsBalance = $this->balanceService->accountBalance($billsAccount);
            $mainBalance = $this->balanceService->accountBalance($mainAccount);
            
            $billAmount = (float) $bill->amount;
            
            Log::info('Attempting to pay bill', [
                'bill_id' => $bill->id,
                'amount' => $billAmount,
                'bills_balance' => $billsBalance,
                'main_balance' => $mainBalance
            ]);
            
            // Check if we have enough funds (Bills bucket first, then Main as fallback)
            $sourceAccount = null;
            if ($billsBalance >= $billAmount) {
                $sourceAccount = $billsAccount;
            } elseif (($billsBalance + $mainBalance) >= $billAmount) {
                $sourceAccount = $billsAccount; // We'll handle partial payment from both accounts
            } else {
                Log::warning('Insufficient funds for bill payment', [
                    'bill_id' => $bill->id,
                    'required' => $billAmount,
                    'available' => $billsBalance + $mainBalance
                ]);
                
                if (!$manualPayment) {
                    // For auto-pay, skip this bill and try again next time
                    return null;
                }
                throw new \Exception('Insufficient funds for bill payment');
            }
            
            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'provider' => 'pesapal', // TODO: Make this configurable
                'direction' => 'out',
                'status' => 'pending',
                'amount' => $billAmount,
                'currency' => $bill->currency,
                'meta' => [
                    'bill_id' => $bill->id,
                    'bill_name' => $bill->name,
                    'merchant_code' => $bill->merchant_code,
                    'account_number' => $bill->account_number
                ]
            ]);
            
            // Create bill payment record
            $billPayment = BillPayment::create([
                'user_bill_id' => $bill->id,
                'transaction_id' => $transaction->id,
                'amount' => $billAmount,
                'currency' => $bill->currency,
                'due_date' => $bill->next_due_date,
                'status' => 'pending'
            ]);
            
            // Debit the accounts (Bills first, then Main if needed)
            $remainingAmount = $billAmount;
            
            // Debit Bills account
            $billsDebit = min($billsBalance, $remainingAmount);
            if ($billsDebit > 0) {
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $billsAccount->id,
                    'entry_type' => 'debit',
                    'amount' => $billsDebit,
                    'description' => "Bill payment: {$bill->name}"
                ]);
                $remainingAmount -= $billsDebit;
            }
            
            // Debit Main account for remainder if needed
            if ($remainingAmount > 0) {
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $mainAccount->id,
                    'entry_type' => 'debit',
                    'amount' => $remainingAmount,
                    'description' => "Bill payment (overflow from Main): {$bill->name}"
                ]);
            }
            
            // TODO: Actually call the payment provider to send money
            // For now, we'll mark as completed immediately for demo
            $transaction->update(['status' => 'succeeded']);
            $billPayment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'provider_response' => ['demo' => 'Payment simulated successfully']
            ]);
            
            // Update bill's next due date and last paid date
            $bill->last_paid_at = now();
            $bill->updateNextDueDate();
            
            return $billPayment;
        });
    }
    
    public function getUserBills(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->bills()->with(['payments' => function ($query) {
            $query->latest()->limit(3);
        }])->orderBy('next_due_date')->get();
    }
}