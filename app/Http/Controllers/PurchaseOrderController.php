<?php
namespace App\Http\Controllers;

use App\Models\SupplierPurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $r)
    {
        return SupplierPurchaseOrder::with('supplier')->latest()->get();
    }

    public function show($id)
    {
        return SupplierPurchaseOrder::with(['supplier', 'items'])->findOrFail($id);
    }
}
