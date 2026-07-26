<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('biolab.auth')->group(function () {
    Route::get('/', [LabController::class, 'index'])->name('lab.index');
    Route::get('/laboratorio', [OrderController::class, 'labQueue'])->middleware('biolab.role:admin,recepcion,laboratorio')->name('orders.lab');
    Route::get('/ordenes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/ordenes/nueva', [OrderController::class, 'create'])->middleware('biolab.role:recepcion,caja')->name('orders.create');
    Route::post('/ordenes', [OrderController::class, 'store'])->middleware('biolab.role:recepcion,caja')->name('orders.store');
    Route::get('/ordenes/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/ordenes/{id}/resultados', [OrderController::class, 'results'])->middleware('biolab.role:laboratorio')->name('orders.results');
    Route::post('/ordenes/{id}/resultados', [OrderController::class, 'saveResults'])->middleware('biolab.role:laboratorio')->name('orders.results.save');
    Route::post('/ordenes/{id}/pago', [OrderController::class, 'pay'])->middleware('biolab.role:caja,recepcion')->name('orders.pay');
    Route::post('/ordenes/{id}/entregar', [OrderController::class, 'deliver'])->middleware('biolab.role:recepcion,laboratorio')->name('orders.deliver');
    Route::post('/ordenes/{id}/anular', [OrderController::class, 'cancel'])->middleware('biolab.role:admin,caja')->name('orders.cancel');
    Route::get('/ordenes/{id}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
    Route::get('/caja', [CashController::class, 'index'])->middleware('biolab.role:caja,recepcion')->name('cash.index');
    Route::post('/caja', [CashController::class, 'store'])->middleware('biolab.role:caja')->name('cash.store');
    Route::post('/caja/{id}/anular', [CashController::class, 'void'])->middleware('biolab.role:admin,caja')->name('cash.void');
    Route::get('/catalogos', [CatalogController::class, 'index'])->middleware('biolab.role:admin,recepcion')->name('catalog.index');
    Route::post('/catalogos/referencias', [CatalogController::class, 'referrer'])->middleware('biolab.role:admin,recepcion')->name('catalog.referrer');
    Route::post('/catalogos/precios', [CatalogController::class, 'price'])->middleware('biolab.role:admin')->name('catalog.price');
    Route::post('/catalogos/examenes', [CatalogController::class, 'exam'])->middleware('biolab.role:admin,laboratorio')->name('catalog.exam');
    Route::delete('/catalogos/examenes/{slug}', [CatalogController::class, 'deleteExam'])->middleware('biolab.role:admin')->name('catalog.exam.delete');
    Route::get('/historial', [LabController::class, 'history'])->name('lab.history');
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('biolab.role:admin')->name('audit.index');
    Route::get('/resultados/{category}/nuevo', [LabController::class, 'create'])->middleware('biolab.role:laboratorio')->name('lab.results.create');
    Route::post('/resultados/{category}/vista-previa', [LabController::class, 'preview'])->middleware('biolab.role:laboratorio')->name('lab.results.preview');
    Route::post('/resultados/{category}/guardar', [LabController::class, 'save'])->middleware('biolab.role:laboratorio')->name('lab.results.save');
    Route::post('/resultados/{category}/pdf', [LabController::class, 'pdf'])->middleware('biolab.role:laboratorio')->name('lab.results.pdf');
    Route::get('/resultados/guardados/{id}', [LabController::class, 'show'])->name('lab.results.show');
    Route::get('/resultados/guardados/{id}/editar', [LabController::class, 'edit'])->middleware('biolab.role:laboratorio')->name('lab.results.edit');
    Route::post('/resultados/guardados/{id}', [LabController::class, 'update'])->middleware('biolab.role:laboratorio')->name('lab.results.update');
    Route::delete('/resultados/guardados/{id}', [LabController::class, 'destroy'])->middleware('biolab.role:admin,laboratorio')->name('lab.results.destroy');
    Route::get('/resultados/guardados/{id}/pdf', [LabController::class, 'savedPdf'])->name('lab.results.saved-pdf');
});
