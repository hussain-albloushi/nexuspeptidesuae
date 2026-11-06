<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        return User::where('role', 'customer')->latest()->get();
    }

    public function show($id)
    {
        return User::where('role', 'customer')->findOrFail($id);
    }
}
