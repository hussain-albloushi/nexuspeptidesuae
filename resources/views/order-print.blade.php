<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice ? 'Order invoice' : 'Orders report' }} · NexusPeptides</title>
    <style>
        * { box-sizing: border-box; } body { font: 14px/1.55 system-ui, sans-serif; color: #000; margin: 0; padding: 32px; background: #fff; }
        main { max-width: 1100px; margin: auto; } header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 3px solid #0B4F8A; padding-bottom: 20px; margin-bottom: 24px; }
        h1 { color: #0B4F8A; font-size: 26px; margin: 0; } h2 { font-size: 20px; } h3 { font-size: 15px; margin: 0 0 8px; }
        p { margin: 4px 0; } button { border: 0; background: #0B4F8A; color: #fff; border-radius: 6px; padding: 12px 18px; font: inherit; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; } th, td { padding: 10px; text-align: left; border-bottom: 1px solid #E2E8F0; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #f5f8fb; font-size: 12px; } .right { text-align: right; white-space: nowrap; } .columns { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .totals { max-width: 380px; margin-left: auto; } .total { font-weight: 700; font-size: 17px; } small { color: #454545; } .filters { padding: 12px; background: #f5f8fb; }
        .notes { white-space: pre-wrap; overflow-wrap: anywhere; } .print-controls { margin: 0 0 24px; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
        @media print { body { padding: 0; font-size: 11px; }.print-controls { display: none; }thead { display: table-header-group; }tr { break-inside: avoid; }@page { margin: 15mm; } }
        @media (max-width: 600px) { body { padding: 16px; }.columns { grid-template-columns: 1fr; }.report { overflow-x: auto; } }
    </style>
</head>
<body>
<main>
    <div class="print-controls"><button type="button" onclick="window.print()">Print / Save as PDF</button><small>Choose “Save as PDF” in your browser's print dialog to export a PDF.</small></div>
    <header><div><h1>NexusPeptides</h1><p>{{ $invoice ? 'Order invoice' : 'Orders report' }}</p></div><div><p>Generated {{ now()->format('d M Y, H:i') }}</p><small>{{ config('app.timezone') }} · AED</small></div></header>
    @if ($invoice)
        @foreach ($orders as $order)
            <h2>Order #{{ $order->order_number ?: $order->id }}</h2>
            <div class="columns">
                <section><h3>Customer</h3><p>{{ $order->user?->name ?? 'Customer unavailable' }}</p><p>{{ $order->user?->email }}</p>
                    @php
                        $shipping = json_decode($order->shipping_address ?? '', true);
                    @endphp
                    <h3 style="margin-top:16px">Shipping address</h3>
                    @if (is_array($shipping))
                        @foreach ($shipping as $key => $value)
                            @if (is_scalar($value) && $value !== '')<p>{{ ucfirst(str_replace('_', ' ', (string) $key)) }}: {{ $value }}</p>@endif
                        @endforeach
                    @else
                        <p>{{ $order->shipping_address ?: 'Not recorded' }}</p>
                    @endif
                </section>
                <section><h3>Order information</h3><p>Date: {{ $order->created_at?->format('d M Y, H:i') }}</p><p>Status: {{ ucfirst($order->status) }}</p><p>Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'pending')) }}</p><p>Method: {{ $order->payment_method ?: 'Not recorded' }}</p><p>Tracking: {{ $order->tracking_number ?: 'Not recorded' }}</p></section>
            </div>
            <table><thead><tr><th>Item</th><th class="right">Quantity</th><th class="right">Unit price</th><th class="right">Amount (AED)</th></tr></thead><tbody>
            @forelse ($order->items as $item)
                <tr><td>{{ $item->name }}</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ number_format((float) $item->price, 2) }}</td><td class="right">{{ number_format((float) $item->total_price, 2) }}</td></tr>
            @empty
                <tr><td colspan="4">No item details recorded.</td></tr>
            @endforelse
            </tbody></table>
            <table class="totals"><tbody><tr><td>Subtotal</td><td class="right">{{ number_format((float) $order->subtotal, 2) }}</td></tr><tr><td>Discount</td><td class="right">{{ number_format((float) $order->discount_amount, 2) }}</td></tr><tr><td>Shipping</td><td class="right">{{ number_format((float) $order->shipping_amount, 2) }}</td></tr>@if ((float) $order->cod_fee > 0)<tr><td>Cash on delivery</td><td class="right">{{ number_format((float) $order->cod_fee, 2) }}</td></tr>@endif<tr><td>Tax (recorded)</td><td class="right">{{ number_format((float) $order->tax_amount, 2) }}</td></tr><tr class="total"><td>Order total (AED)</td><td class="right">{{ number_format((float) $order->total_amount, 2) }}</td></tr></tbody></table>
            <small>Amounts are shown as recorded. Tax may already be included in the subtotal. This invoice does not confirm receipt of payment.</small>
            @if ($order->notes)<h3 style="margin-top:20px">Customer notes</h3><p class="notes">{{ $order->notes }}</p>@endif
        @endforeach
    @else
        <p>{{ $count }} orders · {{ count($filters) ? 'Filtered report' : 'All orders' }}</p>
        @if ($filters)<div class="filters">@foreach ($filters as $key => $value)@if ($value !== null && $value !== '')<span>{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }} · </span>@endif @endforeach</div>@endif
        <div class="report"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Payment</th><th class="right">Total (AED)</th></tr></thead><tbody>
        @forelse ($orders as $order)
            <tr><td>#{{ $order->order_number ?: $order->id }}</td><td>{{ $order->user?->name ?? 'Customer unavailable' }}</td><td>{{ $order->created_at?->format('Y-m-d H:i') }}</td><td>{{ ucfirst($order->status) }}</td><td>{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'pending')) }}</td><td class="right">{{ number_format((float) $order->total_amount, 2) }}</td></tr>
        @empty
            <tr><td colspan="6">No matching orders.</td></tr>
        @endforelse
        </tbody></table></div>
    @endif
</main>
</body>
</html>
