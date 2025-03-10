<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

//Route::middleware('sessionInit')->group(function (){
    //Route::post('/sign_in.json', [UserController::class, 'index']);
//});
Route::get('/xinghan/calendars/', function () {
    echo 'abc';
});