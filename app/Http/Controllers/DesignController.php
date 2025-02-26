<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;

class DesignController extends Controller
{
    public function create()
    {
        return Inertia::render('Design', [
            'products' => Product::all()
        ]);
    }
}