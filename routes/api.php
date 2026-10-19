<?php

use App\Http\Controllers\PcAgentActivityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceDiscoveryController;

Route::post('/pc-agent/activity', [
    PcAgentActivityController::class,
    'store',
])->middleware('throttle:60,1');

Route::post('/device-discovery/upload', [
    DeviceDiscoveryController::class,
    'store',
])->middleware('throttle:10,1');