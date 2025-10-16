<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GeoController;
use App\Http\Controllers\API\LogementController;
use App\Http\Controllers\API\PropertyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});



Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/logements', [LogementController::class, 'index']);
    Route::get('/proprietes', [PropertyController::class, 'index']);


});

Route::middleware(['auth:sanctum','proprietaire'])->prefix('proprietaire')->group(function () {
    Route::get('/regions', [GeoController::class, 'regions']);
    Route::get('/regions/{id}/departements', [GeoController::class, 'departements']);
    Route::get('/departements/{id}/communes', [GeoController::class, 'communes']);

    Route::get('/dashboard', [PropertyController::class, 'dashboard']);
    Route::get('/proprietes', [PropertyController::class, 'allProperty']);
    Route::post('/proprietes', [PropertyController::class, 'store']);
    Route::put('/proprietes/{id}', [PropertyController::class, 'update']);
    Route::delete('/proprietes/{id}', [PropertyController::class, 'destroy']);
    Route::get('/proprietes/count', [PropertyController::class, 'countProperty']);
    Route::get('/proprietes/search', [PropertyController::class, 'search']);

    Route::post('/logements', [LogementController::class, 'store']);
    Route::put('/logements/{id}', [LogementController::class, 'update']);
    Route::delete('/logements/{id}', [LogementController::class, 'destroy']);
    Route::get('/logements/search', [LogementController::class, 'search']);
    Route::get('/proprietes/{proprieteId}/logements', [LogementController::class, 'indexByPropriete']);
    Route::get('/proprietes/{proprieteId}/logements/count', [LogementController::class, 'countByPropriete']);
    Route::patch('/logements/{id}/status', [LogementController::class, 'updateStatus']);
    Route::post('/proprietes/{proprieteId}/logements/{id}/photos', [LogementController::class, 'addPhotos']);

});



