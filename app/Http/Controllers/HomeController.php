<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function __invoke()
    {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
