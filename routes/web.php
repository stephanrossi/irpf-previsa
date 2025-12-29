<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\ImportDeclarationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ClientController::class, 'index'])->name('clients.index');
Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');

Route::get('/import', [ImportDeclarationController::class, 'create'])->name('import.create');
Route::post('/import', [ImportDeclarationController::class, 'store'])->name('import.store');
Route::patch('/declarations/{declaration}/gastos-estimados', [DeclarationController::class, 'updateExpenses'])->name('declarations.update-gastos');
Route::get('/declarations/{declaration}/report', [DeclarationController::class, 'showReport'])->name('declarations.report');
