<?php
namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $r)
    {
        return Supplier::withCount('products')->latest()->get();
    }

    public function show($id)
    {
        return Supplier::with(['products', 'purchaseOrders'])->findOrFail($id);
    }
}
