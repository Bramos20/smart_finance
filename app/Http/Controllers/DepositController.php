<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\DepositService;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller 
{
    public function __construct(private DepositService $deposits){}
    
    public function initiate(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required','numeric','min:1'],
            'provider' => ['required','in:pesapal,flutterwave']
        ]);

        $user = $request->user();
        
        // Log the attempt
        Log::info('Deposit initiation attempt', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'user_phone' => $user->phone ?? 'not_set',
            'amount' => $data['amount'],
            'provider' => $data['provider']
        ]);

        try {
            $intent = $this->deposits->initiate(
                $user, 
                $data['provider'],
                (string)$data['amount']
            );
            
            Log::info('Deposit initiation successful', [
                'user_id' => $user->id,
                'redirect_url' => $intent->link
            ]);
            
            return Inertia::location($intent->link);
            
        } catch (\Exception $e) {
            Log::error('Deposit initiation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors([
                'deposit' => 'Failed to initiate deposit: ' . $e->getMessage()
            ])->withInput();
        }
    }
}