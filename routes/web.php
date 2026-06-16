<?php

use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CreditCardPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedExmpenseController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\VariableIncomeController;
use Illuminate\Support\Facades\Route;

Route::resource('/', DashboardController::class)->name('index', 'dashboard');

Route::resource('/projection', ProjectionController::class);

Route::resource('/credit-cards', CreditCardController::class);
Route::resource('/fixed-expenses', FixedExmpenseController::class);
Route::resource('/salaries', SalaryController::class);
Route::resource('/variable-incomes', VariableIncomeController::class);
Route::resource('/purchases', CreditCardPurchaseController::class);
