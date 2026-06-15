<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'))->name('home');

// ── Auth (public) ──────────────────────────────────────────────────────────────
Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',    [AuthController::class, 'login'])->name('login.submit');
Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');

// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Groups — full CRUD
    Route::resource('groups', GroupController::class);

    // Group membership management
    Route::post(   '/groups/{group}/members',        [GroupController::class, 'addMember'])
         ->name('groups.members.add');
    Route::delete( '/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])
         ->name('groups.members.remove');

    // Expenses — nested under group
    Route::resource('groups.expenses', ExpenseController::class);

    // Quick-toggle routes for review/exclude flags (PATCH, no full form reload needed)
    Route::patch('/expenses/{expense}/toggle-review',
                 [ExpenseController::class, 'toggleReview'])
         ->name('expenses.toggle-review');
    Route::patch('/expenses/{expense}/toggle-exclude',
                 [ExpenseController::class, 'toggleExclude'])
         ->name('expenses.toggle-exclude');

    // Settlements
    Route::get(    '/groups/{group}/settlements',  [SettlementController::class, 'index'])
         ->name('settlements.index');
    Route::post(   '/groups/{group}/settlements',  [SettlementController::class, 'store'])
         ->name('settlements.store');
    Route::delete( '/settlements/{settlement}',    [SettlementController::class, 'destroy'])
         ->name('settlements.destroy');

    // Balances
    Route::get('/groups/{group}/balances',        [BalanceController::class, 'group'])
         ->name('balances.group');
    Route::get('/groups/{group}/balances/{user}', [BalanceController::class, 'user'])
         ->name('balances.user');

    // CSV Import
    Route::get( '/groups/{group}/import',  [ImportController::class, 'showForm'])
         ->name('import.form');
    Route::post('/groups/{group}/import',  [ImportController::class, 'import'])
         ->name('import.run');
    Route::get( '/import/{batch}/report',  [ImportController::class, 'report'])
         ->name('import.report');
});
