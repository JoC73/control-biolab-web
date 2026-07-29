<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('biolab.auth')->group(function () {
    Route::get('/', [LabController::class, 'index'])->name('lab.index');
    Route::get('/laboratorio', [OrderController::class, 'labQueue'])->middleware('biolab.permission:orders.view,results.view')->name('orders.lab');
    Route::get('/ordenes', [OrderController::class, 'index'])->middleware('biolab.permission:orders.view')->name('orders.index');
    Route::get('/ordenes/nueva', [OrderController::class, 'create'])->middleware('biolab.permission:orders.create')->name('orders.create');
    Route::post('/ordenes', [OrderController::class, 'store'])->middleware('biolab.permission:orders.create')->name('orders.store');
    Route::get('/ordenes/{id}', [OrderController::class, 'show'])->middleware('biolab.permission:orders.view')->name('orders.show');
    Route::get('/ordenes/{id}/resultados', [OrderController::class, 'results'])->middleware('biolab.permission:results.create,results.edit')->name('orders.results');
    Route::post('/ordenes/{id}/resultados', [OrderController::class, 'saveResults'])->middleware('biolab.permission:results.create,results.edit')->name('orders.results.save');
    Route::post('/ordenes/{id}/pago', [OrderController::class, 'pay'])->middleware('biolab.permission:payments.create')->name('orders.pay');
    Route::post('/ordenes/{id}/entregar', [OrderController::class, 'deliver'])->middleware('biolab.permission:orders.deliver')->name('orders.deliver');
    Route::post('/ordenes/{id}/anular', [OrderController::class, 'cancel'])->middleware('biolab.permission:orders.cancel')->name('orders.cancel');
    Route::get('/ordenes/{id}/pdf/{exam}', [OrderController::class, 'pdfExam'])->middleware('biolab.permission:results.print')->name('orders.pdf.exam');
    Route::get('/ordenes/{id}/pdf', [OrderController::class, 'pdf'])->middleware('biolab.permission:results.print')->name('orders.pdf');
    Route::get('/ordenes/{id}/imprimir', [OrderController::class, 'print'])->middleware('biolab.permission:results.print')->name('orders.print');
    Route::get('/caja', [CashController::class, 'index'])->middleware('biolab.permission:cash.view')->name('cash.index');
    Route::post('/caja', [CashController::class, 'store'])->middleware('biolab.permission:cash.manage')->name('cash.store');
    Route::post('/caja/{id}/anular', [CashController::class, 'void'])->middleware('biolab.permission:cash.manage')->name('cash.void');
    Route::get('/catalogos', [CatalogController::class, 'index'])->middleware('biolab.permission:catalogs.view')->name('catalog.index');
    Route::post('/catalogos/referencias', [CatalogController::class, 'referrer'])->middleware('biolab.permission:catalogs.view,catalogs.manage')->name('catalog.referrer');
    Route::post('/catalogos/precios', [CatalogController::class, 'price'])->middleware('biolab.permission:catalogs.manage')->name('catalog.price');
    Route::post('/catalogos/examenes', [CatalogController::class, 'exam'])->middleware('biolab.permission:catalogs.manage')->name('catalog.exam');
    Route::delete('/catalogos/examenes/{slug}', [CatalogController::class, 'deleteExam'])->middleware('biolab.permission:catalogs.manage')->name('catalog.exam.delete');
    Route::get('/historial', [LabController::class, 'history'])->middleware('biolab.permission:results.view')->name('lab.history');
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('biolab.permission:audit.view')->name('audit.index');
    Route::get('/admin/usuarios', [UserPermissionController::class, 'index'])->middleware('biolab.permission:users.view')->name('admin.users.index');
    Route::put('/admin/usuarios/{email}', [UserPermissionController::class, 'update'])->middleware('biolab.permission:users.manage')->name('admin.users.update');
    Route::get('/resultados/{category}/nuevo', [LabController::class, 'create'])->middleware('biolab.permission:results.create')->name('lab.results.create');
    Route::post('/resultados/{category}/vista-previa', [LabController::class, 'preview'])->middleware('biolab.permission:results.create,results.edit')->name('lab.results.preview');
    Route::post('/resultados/{category}/guardar', [LabController::class, 'save'])->middleware('biolab.permission:results.create')->name('lab.results.save');
    Route::post('/resultados/{category}/pdf', [LabController::class, 'pdf'])->middleware('biolab.permission:results.print')->name('lab.results.pdf');
    Route::get('/resultados/guardados/{id}', [LabController::class, 'show'])->middleware('biolab.permission:results.view')->name('lab.results.show');
    Route::get('/resultados/guardados/{id}/editar', [LabController::class, 'edit'])->middleware('biolab.permission:results.edit')->name('lab.results.edit');
    Route::post('/resultados/guardados/{id}', [LabController::class, 'update'])->middleware('biolab.permission:results.edit')->name('lab.results.update');
    Route::delete('/resultados/guardados/{id}', [LabController::class, 'destroy'])->middleware('biolab.permission:results.edit')->name('lab.results.destroy');
    Route::get('/resultados/guardados/{id}/pdf', [LabController::class, 'savedPdf'])->middleware('biolab.permission:results.print')->name('lab.results.saved-pdf');
});
