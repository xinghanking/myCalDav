<?php

namespace App\Http;

use App\Http\Middleware\Auth;
use App\Http\Middleware\SessionMiddleware;

class Kernel extends \Illuminate\Foundation\Http\Kernel
{
    protected $middleware = [
        Auth::class,
    ];
    protected $except = ['/'];
}
