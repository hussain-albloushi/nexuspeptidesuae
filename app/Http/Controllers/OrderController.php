<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        return Order::with('user')->latest()->limit(200)->get();
    }

    public function show($id)
    {
        return Order::with(['user', 'items', 'statusHistory'])->findOrFail($id);
    }
}
