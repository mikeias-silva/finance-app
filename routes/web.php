<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CnisController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CreditCardPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedExmpenseController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NubankImportController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\RecurringIncomeController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\VariableExpenseController;
use App\Http\Controllers\VariableIncomeController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login');
// Senha única exposta na internet: sem limite de tentativas, um script testa
// milhares por minuto.
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::resource('/', DashboardController::class)->name('index', 'dashboard');

Route::resource('/projection', ProjectionController::class);

Route::resource('/credit-cards', CreditCardController::class);
Route::resource('/fixed-expenses', FixedExmpenseController::class);
Route::resource('/salaries', SalaryController::class);
Route::resource('/recurring-incomes', RecurringIncomeController::class);
Route::resource('/variable-incomes', VariableIncomeController::class);
Route::resource('/variable-expenses', VariableExpenseController::class);
Route::resource('/purchases', CreditCardPurchaseController::class);

// Mesma importação do comando import:pluggy-nubank, disparada pelo dashboard.
// O caminho não pode começar com 'accounts': RequireAppPassword responde 401 JSON
// nessas rotas, e aqui o esperado é o redirect normal para o login.
Route::post('/imports/nubank', [NubankImportController::class, 'store'])->name('imports.nubank');

Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
Route::get('/accounts/nubank/statement', [AccountController::class, 'nubankStatement'])->name('accounts.nubank.statement');
Route::get('/accounts/caixa/credit-card/statement', [AccountController::class, 'caixaCreditCardStatement'])->name('accounts.caixa.credit-card.statement');
Route::get('/accounts/caixa/credit-card/statement/merged', [AccountController::class, 'caixaCreditCardMergedStatement'])->name('accounts.caixa.credit-card.statement.merged');
Route::get('/accounts/caixa/credit-card/transactions', [AccountController::class, 'caixaCreditCardTransactions'])->name('accounts.caixa.credit-card.transactions');
Route::get('/accounts/credit-cards/recurring', [AccountController::class, 'creditCardRecurringCharges'])->name('accounts.credit-cards.recurring');

Route::get('/cnis', [CnisController::class, 'index'])->name('cnis.index');
Route::post('/cnis/importar', [CnisController::class, 'importar'])->name('cnis.importar');
Route::get('/investments', [InvestmentController::class, 'index'])->name('investments.index');
Route::post('/investments/cdi', [InvestmentController::class, 'storeCdi'])->name('investments.cdi.store');
Route::post('/investments/boxes', [InvestmentController::class, 'storeBox'])->name('investments.boxes.store');
Route::post('/investments/{box}/deposit', [InvestmentController::class, 'deposit'])->name('investments.deposit');
Route::post('/investments/{box}/withdrawal', [InvestmentController::class, 'withdrawal'])->name('investments.withdrawal');
