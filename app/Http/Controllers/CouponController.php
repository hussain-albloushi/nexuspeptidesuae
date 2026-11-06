<?php
namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $r)
    {
        return Coupon::latest()->get();
    }

    public function show($id)
    {
        return Coupon::findOrFail($id);
    }
}
