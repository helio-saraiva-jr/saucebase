<?php

use Illuminate\Support\Facades\Route;
use Modules\Megacombo\Http\Controllers\Api\SimulationController;

Route::middleware(['web', 'auth'])->prefix('megacombo')->name('api.megacombo.')->group(function () {
    Route::post('simulate/price', [SimulationController::class, 'price'])->name('simulate.price');
    Route::post('simulate/probability', [SimulationController::class, 'probability'])->name('simulate.probability');
    Route::post('triage', [SimulationController::class, 'triage'])->name('triage');
    Route::get('financial-simulations', [SimulationController::class, 'listFinancialSimulations'])->name('financial-simulations.index');
    Route::post('financial-simulations', [SimulationController::class, 'storeFinancialSimulation'])->name('financial-simulations.store');
});
