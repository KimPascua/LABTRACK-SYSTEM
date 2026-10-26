<?php

use App\Http\Controllers\PcAgentActivityController;
use App\Http\Controllers\PcAgentRulesController;
use App\Http\Controllers\DeviceDiscoveryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteActivityController;

Route::post('/pc-agent/activity', [
    PcAgentActivityController::class,
    'store',
])->middleware('throttle:60,1');

Route::get('/pc-agent/rules', [
    PcAgentRulesController::class,
    'index',
])->middleware('throttle:60,1');

Route::post('/device-discovery/upload', [
    DeviceDiscoveryController::class,
    'store',
])->middleware('throttle:10,1');

Route::get('/website-agent/rules', [
    WebsiteActivityController::class,
    'rules',
])->middleware('throttle:60,1');

Route::post('/website-agent/activity', [
    WebsiteActivityController::class,
    'store',
])->middleware('throttle:60,1');