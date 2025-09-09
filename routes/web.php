<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\{
    DashboardController, 
    DepositController, 
    AllocationController, 
    BillsController,
    WebhookController
};
use App\Http\Controllers\PesapalWebhookController;

Route::match(['get','post'], '/webhooks/pesapal', [PesapalWebhookController::class, 'handle'])
    ->name('webhooks.pesapal')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]); // webhooks should skip CSRF

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function(){
    // Dashboard
    Route::get('/', [DashboardController::class,'index'])->name('dashboard');
    
    // Deposits
    Route::post('/deposits/initiate', [DepositController::class,'initiate'])->name('deposits.initiate');
    
    // Allocation Rules
    Route::get('/rules', [AllocationController::class,'index'])->name('rules.index');
    Route::post('/rules', [AllocationController::class,'store'])->name('rules.store');
    
    // Bills Management
    Route::get('/bills', [BillsController::class, 'index'])->name('bills.index');
    Route::post('/bills', [BillsController::class, 'store'])->name('bills.store');
    Route::put('/bills/{bill}', [BillsController::class, 'update'])->name('bills.update');
    Route::delete('/bills/{bill}', [BillsController::class, 'destroy'])->name('bills.destroy');
    Route::post('/bills/{bill}/pay-now', [BillsController::class, 'payNow'])->name('bills.pay-now');
});