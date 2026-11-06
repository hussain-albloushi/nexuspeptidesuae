<?php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $r)
    {
        return Category::orderBy('sort_order')->orderBy('name')->get();
    }

    public function show($id)
    {
        return Category::findOrFail($id);
    }
}
