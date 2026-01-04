<?php

namespace App\Http\Controllers;

class ToolController extends Controller
{
    public function index()
    {
        return view('tools.domain-checker');
    }
}
