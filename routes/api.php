<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmazonController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/amazon/test-token',[AmazonController::class, 'testToken']);
Route::post('/amazon/listing',[AmazonController::class, 'createListing']);
Route::get('/amazon/catalog-item',[AmazonController::class, 'getCatalogItem']);