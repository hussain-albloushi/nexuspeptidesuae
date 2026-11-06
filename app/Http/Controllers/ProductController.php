<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $r)
    {
        return Product::with('category')->latest()->get();
    }

    public function show($id)
    {
        return Product::with(['category', 'variants', 'images'])->findOrFail($id);
    }
}
