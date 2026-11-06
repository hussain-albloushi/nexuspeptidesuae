<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $r)
    {
        return Expense::orderByDesc('date')->get();
    }

    public function show($id)
    {
        return Expense::findOrFail($id);
    }
}
