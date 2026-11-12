<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    private const PAYMENT_STATES = ['pending', 'unpaid', 'paid', 'partially_paid', 'failed', 'refunded', 'partially_refunded'];

    public function index(Request $request): Collection|LengthAwarePaginator
    {
        $query = $this->filteredOrders($request);
        $orders = $request->filled('per_page') ? $query->paginate($request->integer('per_page'))->withQueryString() : $query->limit(200)->get();
        $records = $orders instanceof Collection ? $orders : $orders->getCollection();
        $records->each(fn (Order $order) => $this->withTransitions($order));

        return $orders;
    }

    public function show(int $id): Order
    {
        return $this->withTransitions(Order::with([
            'user:id,name,email', 'items', 'statusHistory' => fn ($history) => $history->orderBy('created_at')->orderBy('id'),
            'returnRequests' => fn ($requests) => $requests->orderBy('created_at'),
            'returnRequests.items.orderItem',
        ])->findOrFail($id));
    }

    private function withTransitions(Order $order): Order
    {
        $order->setAttribute('allowed_transitions', Order::$allowedStatusTransitions[$order->status] ?? []);

        return $order;
    }

    private function filteredOrders(Request $request): Builder
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(Order::$allowedStatusTransitions))],
            'payment_status' => ['nullable', Rule::in(self::PAYMENT_STATES)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
            'sort' => ['nullable', Rule::in(['id', 'order_number', 'customer', 'created_at', 'status', 'payment_status', 'total_amount'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
            'ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:orders,id'],
        ]);
        $term = ltrim(trim($data['q'] ?? ''), '#');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $query = Order::with('user:id,name,email')
            ->when($term !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("id LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('user', function (Builder $query) use ($pattern): void {
                            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern]);
                        });
                });
            })
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(isset($data['payment_status']), fn (Builder $query) => $query->where('payment_status', $data['payment_status']))
            ->when(isset($data['start_date']), fn (Builder $query) => $query->whereDate('created_at', '>=', $data['start_date']))
            ->when(isset($data['end_date']), fn (Builder $query) => $query->whereDate('created_at', '<=', $data['end_date']))
            ->when(isset($data['ids']), fn (Builder $query) => $query->whereIn('id', $data['ids']));
        $sort = $data['sort'] ?? 'created_at';
        $direction = $data['direction'] ?? 'desc';
        $column = $sort === 'customer' ? User::select('name')->whereColumn('users.id', 'orders.user_id')->limit(1) : $sort;

        return $query->orderBy($column, $direction)->orderBy('orders.id', $direction);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::$allowedStatusTransitions))],
            'expected_status' => ['required', Rule::in(array_keys(Order::$allowedStatusTransitions))],
            'notes' => ['required_if:status,cancelled', 'nullable', 'string', 'max:2000'],
            'tracking_number' => ['exclude_unless:status,shipped', 'nullable', 'string', 'max:255'],
        ]);
        Order::findOrFail($id);
        $this->changeStatuses([['id' => $id, 'expected_status' => $data['expected_status']]], $data['status'], $data['notes'] ?? null, $data['tracking_number'] ?? null);

        return response()->json(['message' => 'Order status updated successfully.', 'order' => $this->show($id)]);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1', 'max:100'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:orders,id'],
            'orders.*.expected_status' => ['required', Rule::in(array_keys(Order::$allowedStatusTransitions))],
            'status' => ['required', Rule::in(array_keys(Order::$allowedStatusTransitions))],
            'notes' => ['required_if:status,cancelled', 'nullable', 'string', 'max:2000'],
        ]);
        $this->changeStatuses($data['orders'], $data['status'], $data['notes'] ?? null);

        return response()->json(['message' => count($data['orders']).' orders updated successfully.']);
    }

    /** @param array<int, array{id: int, expected_status: string}> $requestedOrders */
    private function changeStatuses(array $requestedOrders, string $status, ?string $notes, ?string $tracking = null): void
    {
        DB::transaction(function () use ($requestedOrders, $status, $notes, $tracking): void {
            $expected = collect($requestedOrders)->keyBy('id');
            $orders = Order::whereIn('id', $expected->keys())->orderBy('id')->lockForUpdate()->get();
            if ($orders->count() !== count($requestedOrders)) {
                throw ValidationException::withMessages(['orders' => 'An order is no longer available. Refresh and try again.']);
            }
            foreach ($orders as $order) {
                if ($order->status !== $expected[$order->id]['expected_status']) {
                    throw ValidationException::withMessages(['orders' => 'Order '.$order->order_number.' changed while you were reviewing it. Refresh before updating.']);
                }
                if (! $order->canTransitionTo($status)) {
                    throw ValidationException::withMessages(['status' => 'Order '.$order->order_number.' cannot move from '.$order->status.' to '.$status.'.']);
                }
            }
            foreach ($orders as $order) {
                $oldStatus = $order->status;
                $order->transitionStatus($status, $notes);
                if (in_array($status, ['shipped', 'delivered', 'cancelled'], true)) {
                    $order->update(['shipping_status' => $status]);
                }
                if ($status === 'shipped' && $tracking !== null) {
                    $order->update(['tracking_number' => $tracking]);
                }
                if ($status === 'cancelled' && in_array($oldStatus, ['pending', 'processing'], true)) {
                    $this->restoreStock($order, 'Cancelled order '.$order->order_number);
                }
            }
        }, 3);
    }

    public function collectPayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'expected_payment_status' => ['required', Rule::in(['pending', 'unpaid'])],
        ]);
        DB::transaction(function () use ($id, $data): void {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'delivered' || $order->payment_status !== $data['expected_payment_status']) {
                throw ValidationException::withMessages(['payment' => 'This order changed or is not an unpaid delivered order. Refresh before continuing.']);
            }
            $this->recordCollectedPayment($order, 'Payment received in full and manually confirmed by an administrator. Method: '.($order->payment_method ?? 'not recorded').'.');
        }, 3);

        return response()->json(['message' => 'Payment recorded as paid.', 'order' => $this->show($id)]);
    }

    private function recordCollectedPayment(Order $order, string $notes = 'Cash-on-delivery payment received in full and confirmed by an administrator.'): void
    {
        $order->update(['payment_status' => 'paid']);
        $order->statusHistory()->create([
            'old_status' => $order->status,
            'new_status' => $order->status,
            'notes' => $notes,
        ]);
    }

    /**
     * Record a refund the admin performed with the payment provider (e.g. the
     * Stripe dashboard or cash handed back). This is bookkeeping: it flips the
     * order's payment state and emails the customer, but it does not move
     * money or touch stock.
     */
    public function recordRefund(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($id, $data): void {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Only paid orders can be refunded. Record the payment first if cash on delivery was collected.',
                ]);
            }

            $alreadyRefunded = $order->payment_status === 'partially_refunded' ? $this->refundedSoFar($order) : 0.0;
            $amount = round((float) $data['amount'], 2);
            if ($alreadyRefunded + $amount > (float) $order->total_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Refunds cannot exceed the order total. AED '.number_format(max(0, (float) $order->total_amount - $alreadyRefunded), 2).' can still be refunded.',
                ]);
            }

            $total = (float) $order->total_amount;
            $state = round($alreadyRefunded + $amount, 2) >= $total ? 'refunded' : 'partially_refunded';
            $order->update(['payment_status' => $state]);
            $order->statusHistory()->create([
                'old_status' => $order->status,
                'new_status' => $order->status,
                'notes' => 'Refund of AED '.number_format($amount, 2).' recorded'.($state === 'partially_refunded' ? ' (partial).' : '.').' '.$data['notes'],
            ]);
        }, 3);

        return response()->json(['message' => 'Refund recorded.', 'order' => $this->show($id)]);
    }

    /**
     * Approve or reject a customer return request. Approval records the refund
     * state, emails the customer, and restocks the returned items. A partial
     * return keeps the order delivered; when every item of the order has been
     * returned the order is cancelled as fully returned.
     */
    public function respondReturn(Request $request, int $id, int $returnId): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'refund_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($id, $returnId, $data): void {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $return = ReturnRequest::with('items.orderItem')
                ->whereKey($returnId)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== ReturnRequest::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'decision' => 'This return request was already handled.',
                ]);
            }
            if ($order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'decision' => 'Only delivered orders can be returned. Refresh the order and try again.',
                ]);
            }

            $notes = trim((string) ($data['decision_notes'] ?? ''));

            if ($data['decision'] === 'rejected') {
                $return->update(['status' => ReturnRequest::STATUS_REJECTED, 'decision_notes' => $notes ?: null]);
                $order->statusHistory()->create([
                    'old_status' => $order->status,
                    'new_status' => $order->status,
                    'notes' => 'Return request rejected.'.($notes !== '' ? ' '.$notes : ''),
                ]);

                return;
            }

            // Requests created before item-level returns existed have no lines:
            // they are whole-order returns and keep the old approval behaviour.
            $legacy = $return->items->isEmpty();

            // Refunds cover the returned items only — shipping and cash-on-
            // delivery fees are not refunded on partial returns. When every
            // item comes back (or the request is a legacy whole-order one),
            // the remaining order balance, including those fees, is refunded.
            $claimed = $return->refundAmount();
            $fullyReturned = $legacy || $order->items->every(fn ($item) => $item->returned_quantity >= $item->quantity);
            $alreadyRefunded = $this->refundedSoFar($order);
            $balance = round(max(0, (float) $order->total_amount - $alreadyRefunded), 2);
            $cap = $fullyReturned ? $balance : min($claimed, $balance);
            $amount = isset($data['refund_amount']) ? round((float) $data['refund_amount'], 2) : $cap;
            if ($amount > $cap) {
                throw ValidationException::withMessages([
                    'refund_amount' => $fullyReturned
                        ? 'Only AED '.number_format($balance, 2).' remains to be refunded on this order.'
                        : 'The returned items add up to AED '.number_format($cap, 2).'; refunds above that need a manual refund record.',
                ]);
            }

            $lines = $legacy ? 'whole order' : $return->items->map(fn (ReturnRequestItem $item) => $item->quantity.' × '.$item->orderItem->name)->implode(', ');
            $historyNotes = 'Return approved ('.$lines.'). A refund of AED '.number_format($amount, 2).' was issued'.($notes !== '' ? ': '.$notes : '.');
            $state = round($alreadyRefunded + $amount, 2) >= (float) $order->total_amount ? 'refunded' : 'partially_refunded';
            $order->update(['payment_status' => $state]);

            if ($fullyReturned) {
                $order->transitionStatus('cancelled', $historyNotes);
                $order->update(['shipping_status' => 'cancelled']);
            } else {
                // A partial return keeps the order delivered and shippable as-is;
                // the customer keeps the other items.
                $order->statusHistory()->create([
                    'old_status' => $order->status,
                    'new_status' => $order->status,
                    'notes' => $historyNotes,
                ]);
            }
            if ($legacy) {
                // Whole-order request: restock everything sold on this order.
                $this->restoreStock($order, 'Returned order '.$order->order_number);
            } else {
                $this->restoreReturnedStock($return, 'Returned order '.$order->order_number);
            }

            $return->update(['status' => ReturnRequest::STATUS_APPROVED, 'refund_amount' => $amount, 'decision_notes' => $notes ?: null]);
        }, 3);

        return response()->json(['message' => 'Return request '.$data['decision'].'.', 'order' => $this->show($id)]);
    }

    /** Sum of refunds already recorded: manual refund notes plus approved returns. */
    private function refundedSoFar(Order $order): float
    {
        $recorded = (float) $order->statusHistory()
            ->where('notes', 'like', 'Refund of AED%')
            ->get()
            ->sum(fn ($history) => (float) preg_replace('/.*Refund of AED ([0-9.]+).*/', '$1', $history->notes));
        $returned = (float) $order->returnRequests()
            ->where('status', ReturnRequest::STATUS_APPROVED)
            ->sum('refund_amount');

        return round($recorded + $returned, 2);
    }

    private function restoreStock(Order $order, string $reason): void
    {
        $sales = InventoryTransaction::where('type', 'sale')->where('reason', 'Order '.$order->order_number)
            ->whereColumn('quantity_before', '>', 'quantity_after')->orderBy('product_id')->get()->groupBy('product_id');
        foreach ($sales as $productId => $movements) {
            $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }
            // Items already come back through approved return requests, so
            // their quantities were restored earlier — never restore twice.
            $returned = (int) InventoryTransaction::where('type', 'return')
                ->where('reason', 'Returned order '.$order->order_number)
                ->where('product_id', $productId)
                ->get()
                ->sum(fn (InventoryTransaction $movement): int => $movement->quantity_after - $movement->quantity_before);
            $quantity = $movements->sum(fn (InventoryTransaction $movement): int => $movement->quantity_before - $movement->quantity_after) - $returned;
            if ($quantity <= 0) {
                continue;
            }
            $before = $product->stock_quantity;
            $after = $before + $quantity;
            $product->update([
                'stock_quantity' => $after,
                'stock_status' => $after <= 0 ? 'out_of_stock' : ($after <= $product->low_stock_threshold ? 'low_stock' : 'in_stock'),
            ]);
            InventoryTransaction::create([
                'product_id' => $productId, 'quantity_before' => $before, 'quantity_after' => $after,
                'type' => 'return', 'reason' => $reason,
            ]);
        }
    }

    /** Restock only the quantities the approved return request covers. */
    private function restoreReturnedStock(ReturnRequest $return, string $reason): void
    {
        $return->loadMissing('items.orderItem');
        $quantities = $return->items
            ->groupBy(fn (ReturnRequestItem $item) => $item->orderItem->product_id)
            ->map(fn ($lines) => (int) $lines->sum('quantity'));

        foreach ($quantities as $productId => $quantity) {
            $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }
            $before = $product->stock_quantity;
            $after = $before + $quantity;
            $product->update([
                'stock_quantity' => $after,
                'stock_status' => $after <= 0 ? 'out_of_stock' : ($after <= $product->low_stock_threshold ? 'low_stock' : 'in_stock'),
            ]);
            InventoryTransaction::create([
                'product_id' => $productId, 'quantity_before' => $before, 'quantity_after' => $after,
                'type' => 'return', 'reason' => $reason,
            ]);
        }
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'print'])]]);
        $query = $this->filteredOrders($request);
        if ($request->input('format') === 'print') {
            return response()->view('order-print', [
                'invoice' => false, 'orders' => $query->lazy(200), 'count' => (clone $query)->count(),
                'filters' => $request->only(['q', 'status', 'payment_status', 'start_date', 'end_date']),
            ])->header('Cache-Control', 'no-store');
        }

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Order ID', 'Order number', 'Customer', 'Email', 'Date', 'Status', 'Payment', 'Total (AED)', 'Tracking number'], ',', '"', '');
            foreach ($query->lazy(200) as $order) {
                $cells = [$order->id, $order->order_number, $order->user?->name, $order->user?->email,
                    $order->created_at?->toIso8601String(), $order->status, $order->payment_status, $order->total_amount, $order->tracking_number];
                $cells = array_map(static function (mixed $value): string {
                    $value = (string) ($value ?? '');

                    return preg_match('/^(?:[\s]*[=+\-@]|[\t\r\n])/', $value) ? "'".$value : $value;
                }, $cells);
                fputcsv($stream, $cells, ',', '"', '');
            }
            fclose($stream);
        }, 'orders-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function invoice(int $id): Response
    {
        return response()->view('order-print', [
            'invoice' => true, 'orders' => collect([$this->show($id)]), 'count' => 1, 'filters' => [],
        ])->header('Cache-Control', 'no-store');
    }
}
