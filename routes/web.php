<?php

use App\Http\Controllers\ManualScrapeController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('leads.index'));

// Manual Scraper Interface
Route::get('/manual-scrape', [ManualScrapeController::class, 'index'])->name('manual.index');
Route::post('/manual-scrape/process', [ManualScrapeController::class, 'process'])->name('manual.process');

Route::name('leads.')->prefix('leads')->group(function () {
    Route::get('/', [LeadController::class, 'index'])->name('index');
    Route::post('/scrape', [LeadController::class, 'scrape'])->name('scrape');
    Route::get('/export', [LeadController::class, 'export'])->name('export');
    Route::get('/{lead}', [LeadController::class, 'show'])->name('show');
    Route::delete('/{lead}', [LeadController::class, 'destroy'])->name('destroy');
    Route::post('/{lead}/retry', [LeadController::class, 'retry'])->name('retry');
    Route::post('/{lead}/sync', [LeadController::class, 'sync'])->name('sync');
});
