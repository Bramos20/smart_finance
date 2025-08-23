<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\{DashboardController, DepositController, AllocationController};

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function(){
    Route::get('/', [DashboardController::class,'index'])->name('dashboard');
    Route::post('/deposits/initiate', [DepositController::class,'initiate'])->name('deposits.initiate');
    Route::get('/rules', [AllocationController::class,'index'])->name('rules.index');
    Route::post('/rules', [AllocationController::class,'store'])->name('rules.store');
});
