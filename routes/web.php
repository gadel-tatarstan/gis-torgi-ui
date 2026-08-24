<?php

use App\Http\Controllers\GpzuController;
use App\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LotController::class, 'index'])->name('lots.index');
Route::get('/api/lots/fetch', [LotController::class, 'fetchLots'])->name('lots.fetch');
Route::get('/api/lots/{id}/detail', [LotController::class, 'showLotDetail'])->name('lots.detail');
Route::get('/api/lots/{id}/full-detail', [LotController::class, 'fetchLotDetail'])->name('lots.full-detail');
Route::post('/api/lots/not-interested', [LotController::class, 'markNotInterested'])->name('lots.not-interested');
Route::post('/api/lots/viewed', [LotController::class, 'markViewed'])->name('lots.viewed');
Route::get('/api/polygon', [LotController::class, 'fetchPolygon'])->name('lots.polygon');
Route::post('/api/lots/add-to-yougile', [LotController::class, 'addToYougile'])->name('lots.add-to-yougile');
Route::put('/api/lots/{id}/comment', [LotController::class, 'saveComment'])->name('lots.save-comment');
Route::get('/api/download-file', [LotController::class, 'downloadFile'])->name('lots.download-file');
Route::get('/api/preview-file', [LotController::class, 'previewFile'])->name('lots.preview-file');
Route::get('/settings', [LotController::class, 'settings'])->name('lots.settings');
Route::post('/settings', [LotController::class, 'saveSettings'])->name('lots.save-settings');

// ГПЗУ routes (conditionally registered)
if (config('gpzu.enabled', true)) {
    Route::get('/api/lots/{id}/gpzu', [GpzuController::class, 'getData'])->name('lots.gpzu-data');
    Route::post('/api/gpzu/process', [GpzuController::class, 'process'])->name('lots.gpzu-process');
    Route::get('/api/lots/{id}/gpzu/status', [GpzuController::class, 'status'])->name('lots.gpzu-status');
    Route::get('/api/lots/{id}/gpzu/page/{page}', [GpzuController::class, 'pdfPage'])->name('lots.gpzu-page');
    Route::get('/api/lots/{id}/gpzu/appendix', [GpzuController::class, 'appendixPdf'])->name('lots.gpzu-appendix');
}
