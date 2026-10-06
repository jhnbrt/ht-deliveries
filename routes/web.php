<?php

use App\Http\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DeliveryController::class, 'dashboard'])->name('dashboard');
Route::get('/deliveries/upload', [DeliveryController::class, 'create'])->name('deliveries.create');
Route::post('/deliveries', [DeliveryController::class, 'store'])->middleware('throttle:20,1')->name('deliveries.store');
Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
Route::get('/export', [DeliveryController::class, 'export'])->name('deliveries.export');
Route::get('/export/csv', [DeliveryController::class, 'csv'])->name('deliveries.csv');
Route::view('/help', 'help')->name('help');
Route::get('/deliveries/{delivery}/photo', [DeliveryController::class, 'photo'])->whereNumber('delivery')->name('deliveries.photo');
Route::get('/deliveries/{delivery}/review', [DeliveryController::class, 'edit'])->whereNumber('delivery')->name('deliveries.edit');
Route::put('/deliveries/{delivery}', [DeliveryController::class, 'update'])->whereNumber('delivery')->name('deliveries.update');
