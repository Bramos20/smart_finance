<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\{BillService, BalanceService};
use App\Models\UserBill;

class BillsController extends Controller
{
    public function __construct(
        private BillService $billService,
        private BalanceService $balanceService
    ) {}
    
    public function index(Request $request)
    {
        $user = $request->user();
        $bills = $this->billService->getUserBills($user);
        $balances = $this->balanceService->userBalances($user);
        
        return Inertia::render('Bills/Index', [
            'bills' => $bills->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'name' => $bill->name,
                    'category' => $bill->category,
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'frequency' => $bill->frequency,
                    'due_day' => $bill->due_day,
                    'next_due_date' => $bill->next_due_date?->format('Y-m-d'),
                    'auto_pay' => $bill->auto_pay,
                    'active' => $bill->active,
                    'is_due' => $bill->isDue(),
                    'recent_payments' => $bill->payments->map(fn($p) => [
                        'id' => $p->id,
                        'amount' => $p->amount,
                        'status' => $p->status,
                        'paid_at' => $p->paid_at?->format('Y-m-d H:i'),
                    ])
                ];
            }),
            'balances' => $balances,
            'categories' => [
                'utilities' => 'Utilities',
                'subscriptions' => 'Subscriptions', 
                'loans' => 'Loans',
                'insurance' => 'Insurance',
                'rent' => 'Rent/Mortgage',
                'other' => 'Other'
            ]
        ]);
    }
    
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:utilities,subscriptions,loans,insurance,rent,other'],
            'amount' => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'merchant_code' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'auto_pay' => ['boolean']
        ]);
        
        $bill = $this->billService->createBill($request->user(), $data);
        
        return back()->with('success', 'Bill created successfully');
    }
    
    public function update(Request $request, UserBill $bill)
    {
        $this->authorize('update', $bill);
        
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:utilities,subscriptions,loans,insurance,rent,other'],
            'amount' => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'merchant_code' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'auto_pay' => ['boolean'],
            'active' => ['boolean']
        ]);
        
        $bill->update($data);
        $bill->updateNextDueDate();
        
        return back()->with('success', 'Bill updated successfully');
    }
    
    public function payNow(Request $request, UserBill $bill)
    {
        $this->authorize('update', $bill);
        
        try {
            $payment = $this->billService->payBill($bill, true);
            
            if ($payment) {
                return back()->with('success', 'Bill paid successfully');
            } else {
                return back()->withErrors(['payment' => 'Unable to process payment. Please check your account balance.']);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }
    
    public function destroy(UserBill $bill)
    {
        $this->authorize('delete', $bill);
        
        $bill->delete();
        
        return back()->with('success', 'Bill deleted successfully');
    }
}