<?php

use App\Http\Controllers\ExcelController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook',[\App\Http\Controllers\BotController::class,'handle'])->name('handle');
//Route::get('/users',[\App\Http\Controllers\BotController::class,'index'])->name('index');
//Route::get('/export-users', [ExcelController::class, 'exportUsers']);

Route::get('/',function() {
   return view('welcome'); 
});
