<?php

use Illuminate\Support\Facades\Route;
use RankCrew\Laravel\Http\Controllers\RankcrewController;

Route::post('/rankcrew/login', [RankcrewController::class, 'login']);
Route::get('/session/token', [RankcrewController::class, 'token']);
Route::post('/api/rankcrew', [RankcrewController::class, 'create']);
Route::get('/api/rankcrew/categories', [RankcrewController::class, 'categories']);
