<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\OpnameController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\RabBudgetController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SpkController;
use App\Http\Controllers\Api\ProjectController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Workflow C: Goods Receipt & Opname
Route::get('/goods-receipts', [GoodsReceiptController::class, 'index']);
Route::get('/pos/{poId}/goods-receipts', [GoodsReceiptController::class, 'getByPo']);
Route::post('/goods-receipts', [GoodsReceiptController::class, 'store']);
Route::get('/opnames', [OpnameController::class, 'index']);
Route::post('/opnames', [OpnameController::class, 'store']);

// Workflow C: Invoices
Route::get('/invoices', [InvoiceController::class, 'index']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::put('/invoices/{id}/engineer-verify', [InvoiceController::class, 'verifyEngineer']);
Route::put('/invoices/{id}/finance-verify', [InvoiceController::class, 'verifyFinance']);
Route::put('/invoices/{id}/manager-approve', [InvoiceController::class, 'approveManager']);
Route::post('/invoices/{id}/payments', [InvoiceController::class, 'executePayment']);
// RAB Data
Route::post('/rab/preview', [RabBudgetController::class, 'preview']);
Route::post('/rab/import', [RabBudgetController::class, 'import']);
Route::get('/projects/{projectId}/rab', [RabBudgetController::class, 'index']);

// Workflow A: Pengadaan & Kontrak
Route::get('/pos', [PurchaseOrderController::class, 'index']);
Route::post('/pos', [PurchaseOrderController::class, 'store']);
Route::get('/spks', [SpkController::class, 'index']);
Route::post('/spks', [SpkController::class, 'store']);

// Master Data
Route::get('/projects', [ProjectController::class, 'index']);
Route::get('/projects/{id}', [ProjectController::class, 'show']);
