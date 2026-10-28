<?php

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderTrackingController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/products', [ProductController::class, 'index']);

    Route::post('/carts', [CartController::class, 'store']);
    Route::get('/carts/{token}', [CartController::class, 'show']);
    Route::post('/carts/{token}/items', [CartController::class, 'addItem']);
    Route::delete('/carts/{token}/items/{product}', [CartController::class, 'removeItem']);

    Route::post('/carts/{token}/checkout', [CheckoutController::class, 'createOrder']);
    Route::post('/orders/{order}/verify', [CheckoutController::class, 'verifyPayment']);
    Route::post('/orders/track', [OrderTrackingController::class, 'show'])->middleware('throttle:6,1');
});
