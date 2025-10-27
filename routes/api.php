<?php

use Illuminate\Support\Facades\Route;
use Us\PaymentModuleManager\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aqui é onde você pode registrar as rotas da API para o seu pacote.
|
*/

Route::post('payment/process', [PaymentController::class, 'process'])
    ->name('payment.process');
