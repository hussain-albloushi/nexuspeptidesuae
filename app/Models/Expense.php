<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model {
    protected $fillable = ['name','category','date','amount','currency','vat_inclusive','vat_amount','notes','supplier_payee','payment_method','description','attachment_path','vat_status'];
    protected $casts = ['date'=>'date','amount'=>'decimal:2','vat_amount'=>'decimal:2','vat_inclusive'=>'boolean'];
}
