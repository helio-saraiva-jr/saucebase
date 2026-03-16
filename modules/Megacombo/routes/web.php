<?php

use Illuminate\Support\Facades\Route;
use Modules\Megacombo\Http\Controllers\WorkflowController;

Route::middleware('auth')->prefix('megacombo')->name('megacombo.')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('dashboard')->name('megacombo.')->group(function () {
    Route::get('/calculadora-custo', [WorkflowController::class, 'financialCalculator'])->name('financial-calculator');
    Route::get('/probabilidades', [WorkflowController::class, 'probabilityEngine'])->name('probability-engine');
    Route::get('/pos-venda-alertas', [WorkflowController::class, 'postSaleCenter'])->name('post-sale-center');
    Route::get('/operacao', [WorkflowController::class, 'operation'])->name('operation');
    Route::get('/nova-acao', [WorkflowController::class, 'newAction'])->name('actions.create');
    Route::get('/pipeline', [WorkflowController::class, 'pipeline'])->name('pipeline');
});

Route::middleware(['auth', 'verified', 'role:user'])->prefix('cliente')->name('megacombo.')->group(function () {
    Route::get('/portal', [WorkflowController::class, 'clientPortal'])->name('client-portal');
});
