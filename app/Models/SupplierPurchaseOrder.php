<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseOrder extends Model
{
    protected $fillable = ['supplier_id', 'po_number', 'po_date', 'expected_delivery', 'currency', 'exchange_rate', 'total_amount', 'status', 'notes', 'attachments_path', 'shipping_cost', 'customs_cost', 'additional_charges'];

    protected $casts = ['po_date' => 'date', 'expected_delivery' => 'date', 'total_amount' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'customs_cost' => 'decimal:2', 'additional_charges' => 'decimal:2'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function expense()
    {
        return $this->hasOne(Expense::class, 'purchase_order_id');
    }
}
