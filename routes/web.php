<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ZatcaSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/invoices');

Route::prefix('zatca')->name('zatca.')->controller(ZatcaSettingController::class)->group(function () {
    Route::get('/', 'edit')->name('edit');
    Route::put('/', 'update')->name('update');
    Route::post('/csr', 'generateCsr')->name('csr');
    Route::post('/compliance-csid', 'complianceCsid')->name('compliance-csid');
    Route::post('/compliance-checks', 'complianceChecks')->name('compliance-checks');
    Route::post('/production-csid', 'productionCsid')->name('production-csid');
});

Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
Route::post('/invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
Route::get('/invoices/{invoice}/xml/{version?}', [InvoiceController::class, 'xml'])
    ->whereIn('version', ['signed', 'cleared'])
    ->name('invoices.xml');
