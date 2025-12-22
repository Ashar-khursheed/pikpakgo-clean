<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to PikPakGo API',
        'version' => '1.0.0',
        'documentation' => url('/api/documentation')
    ]);
});
