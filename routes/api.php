<?php

use App\Http\Controllers\PcAgentActivityController;
use Illuminate\Support\Facades\Route;

Route::post('/pc-agent/activity', [
    PcAgentActivityController::class,
    'store',
])->middleware('throttle:60,1');