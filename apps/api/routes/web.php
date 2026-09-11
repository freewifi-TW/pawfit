<?php

use Illuminate\Support\Facades\Route;

// 所有頁面由 Nuxt 負責；Laravel 只服務 /api/*、/sanctum/* 與 /up。
Route::get('/', fn () => response()->json(['app' => 'Pawfit API', 'status' => 'ok']));
