<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\DepositService;

class DepositController extends Controller {
    public function __construct(private DepositService $deposits){}
    public function initiate(Request $request){
        $data = $request->validate([
            'amount'=>['required','numeric','min:1'],
            'provider'=>['required','in:pesapal,flutterwave']
        ]);
        $intent = $this->deposits->initiate($request->user(), $data['provider'],
        (string)$data['amount']);
        return Inertia::location($intent->link); // redirect to provider (devstub link)
    }
}
