<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\HeroMediaController;
use App\Http\Controllers\Api\Admin\ProductSalesStatsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RestaurantSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/products', ProductController::class);
Route::get('/categories', CategoryController::class);
Route::get('/restaurant-settings', [RestaurantSettingsController::class, 'show']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/quote', [OrderController::class, 'quote'])->middleware('throttle:quotes');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::prefix('/admin')->middleware('admin')->group(function () {
        Route::post('/hero-media', [HeroMediaController::class, 'update']);
        Route::put('/restaurant-settings', [RestaurantSettingsController::class, 'update']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/stats/product-sales', ProductSalesStatsController::class);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('/orders/{order}/accept', [AdminOrderController::class, 'accept']);
        Route::patch('/orders/{order}/reject', [AdminOrderController::class, 'reject']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{product}', [AdminProductController::class, 'show']);
        Route::put('/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('/products/{product}/toggle-availability', [AdminProductController::class, 'toggleAvailability']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    });
});
