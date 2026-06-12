<?php

use Illuminate\Support\Facades\Route;

// ALB health check — always returns 200
Route::get('/_ping', function () {
    return response()->json(['status' => 'ok'], 200);
});
