<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\BalanceService;
class DashboardController extends Controller {
    public function __construct(private BalanceService $balances){}
    public function index(Request $request){
        $user = $request->user();
        $accounts = $user->accounts()->get(['id','name','slug']);
        $balances = $this->balances->userBalances($user);
        $tx = $user->transactions()->latest()->limit(10)->get(['id','amount','currency','status','provider','created_at']);
        return Inertia::render('Dashboard/Index',compact('accounts','balances','tx'));
    }
}