<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /** How many records of each kind to return per search. */
    private const PER_TYPE = 5;

    /**
     * Admin panel sections a search term can navigate straight to, keyed by
     * the route path, with the words that should surface them.
     *
     * @var array<string, array{title: string, terms: array<int, string>}>
     */
    private const SECTIONS = [
        '/admin' => ['title' => 'Dashboard', 'terms' => ['dashboard', 'home', 'overview']],
        '/admin/orders' => ['title' => 'Orders', 'terms' => ['order', 'orders']],
        '/admin/products' => ['title' => 'Products', 'terms' => ['product', 'products', 'catalog']],
        '/admin/categories' => ['title' => 'Categories', 'terms' => ['category', 'categories']],
        '/admin/inventory' => ['title' => 'Inventory', 'terms' => ['inventory', 'stock']],
        '/admin/customers' => ['title' => 'Customers', 'terms' => ['customer', 'customers']],
        '/admin/purchase-orders' => ['title' => 'Purchase Orders', 'terms' => ['purchase order', 'purchase orders', 'po']],
        '/admin/suppliers' => ['title' => 'Suppliers', 'terms' => ['supplier', 'suppliers']],
        '/admin/coupons' => ['title' => 'Coupons', 'terms' => ['coupon', 'coupons', 'discount', 'voucher']],
        '/admin/accounting' => ['title' => 'Accounting', 'terms' => ['accounting', 'expense', 'expenses', 'finance']],
        '/admin/sales-report' => ['title' => 'Sales Report', 'terms' => ['sales report', 'report', 'reports']],
        '/admin/settings' => ['title' => 'Settings', 'terms' => ['settings', 'configuration', 'smtp', 'mail']],
        '/admin/email-templates' => ['title' => 'Email Templates', 'terms' => ['email template', 'email templates', 'templates']],
        '/admin/contents' => ['title' => 'Website Content', 'terms' => ['content', 'contents', 'website content', 'page']],
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $term = trim($data['q'] ?? '');

        if ($term === '') {
            return response()->json(['results' => [], 'sections' => []]);
        }

        $needle = mb_strtolower($term);

        return response()->json([
            'results' => $this->records($needle),
            'sections' => $this->sections($needle),
        ]);
    }

    /**
     * Matching records across every searchable admin entity. Each entry names
     * the admin section it belongs to and the exact filter to open it with.
     *
     * @return array<int, array<string, mixed>>
     */
    private function records(string $needle): array
    {
        $pattern = $this->like($needle);

        $orders = Order::with('user:id,name,email')
            ->where(fn (Builder $q) => $q
                ->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("id LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('user', fn (Builder $u) => $u
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern])))
            ->latest('id')->take(self::PER_TYPE)->get();

        $products = Product::with('category:id,name')
            ->where(fn (Builder $q) => $q
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern]))
            ->orderBy('name')->take(self::PER_TYPE)->get();

        $customers = User::where('role', 'customer')
            ->where(fn (Builder $q) => $q
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(phone) LIKE ? ESCAPE '!'", [$pattern]))
            ->orderBy('name')->take(self::PER_TYPE)->get();

        $coupons = Coupon::whereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$pattern])
            ->latest('id')->take(self::PER_TYPE)->get();

        $suppliers = Supplier::where(fn (Builder $q) => $q
            ->whereRaw("LOWER(COALESCE(trading_name, legal_name)) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(legal_name) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(contact_person) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern]))
            ->orderBy('id')->take(self::PER_TYPE)->get();

        $purchaseOrders = SupplierPurchaseOrder::with('supplier:id,legal_name,trading_name')
            ->where(fn (Builder $q) => $q
                ->whereRaw("LOWER(po_number) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('supplier', fn (Builder $s) => $s
                    ->whereRaw("LOWER(legal_name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(trading_name) LIKE ? ESCAPE '!'", [$pattern])))
            ->latest('id')->take(self::PER_TYPE)->get();

        $categories = Category::where(fn (Builder $q) => $q
            ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(slug) LIKE ? ESCAPE '!'", [$pattern]))
            ->orderBy('name')->take(self::PER_TYPE)->get();

        $expenses = Expense::where(fn (Builder $q) => $q
            ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(category) LIKE ? ESCAPE '!'", [$pattern]))
            ->latest('date')->take(self::PER_TYPE)->get();

        $records = [];

        foreach ($orders as $order) {
            $records[] = [
                'id' => $order->id,
                'type' => 'orders',
                'name' => (string) $order->order_number,
                'status' => (string) ($order->status ?? ''),
                'detail' => 'Customer: '.($order->user?->name ?? '—').' · AED '.number_format((float) $order->total_amount, 2),
                'link' => ['path' => '/admin/orders', 'query' => ['q' => '#'.$order->order_number]],
            ];
        }

        foreach ($products as $product) {
            $records[] = [
                'id' => $product->id,
                'type' => 'products',
                'name' => (string) $product->name,
                'status' => $product->is_active ? 'Active' : 'Draft',
                'detail' => 'SKU: '.($product->sku ?: '—').($product->category ? ' · '.$product->category->name : ''),
                'link' => ['path' => '/admin/products', 'query' => ['q' => (string) ($product->sku ?: $product->name)]],
            ];
        }

        foreach ($customers as $customer) {
            $records[] = [
                'id' => $customer->id,
                'type' => 'customers',
                'name' => (string) $customer->name,
                'status' => '',
                'detail' => (string) ($customer->email ?? '—'),
                'link' => ['path' => '/admin/customers', 'query' => ['q' => (string) ($customer->email ?: $customer->name)]],
            ];
        }

        foreach ($coupons as $coupon) {
            $records[] = [
                'id' => $coupon->id,
                'type' => 'coupons',
                'name' => (string) $coupon->code,
                'status' => $coupon->is_active ? 'Active' : 'Inactive',
                'detail' => ucfirst((string) $coupon->type).' · '.($coupon->usage_count ?? 0).'/'.($coupon->usage_limit ?? '∞'),
                'link' => ['path' => '/admin/coupons', 'query' => ['q' => (string) $coupon->code]],
            ];
        }

        foreach ($suppliers as $supplier) {
            $records[] = [
                'id' => $supplier->id,
                'type' => 'suppliers',
                'name' => (string) ($supplier->trading_name ?: $supplier->legal_name),
                'status' => '',
                'detail' => (string) ($supplier->email ?: $supplier->phone ?: '—'),
                'link' => ['path' => '/admin/suppliers', 'query' => ['q' => (string) ($supplier->trading_name ?: $supplier->legal_name)]],
            ];
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            $supplierName = $purchaseOrder->supplier?->trading_name ?: $purchaseOrder->supplier?->legal_name ?: '—';
            $records[] = [
                'id' => $purchaseOrder->id,
                'type' => 'purchase-orders',
                'name' => (string) $purchaseOrder->po_number,
                'status' => (string) ($purchaseOrder->status ?? ''),
                'detail' => 'Supplier: '.$supplierName.' · AED '.number_format((float) ($purchaseOrder->total_amount ?? 0), 2),
                'link' => ['path' => '/admin/purchase-orders', 'query' => ['q' => (string) $purchaseOrder->po_number]],
            ];
        }

        foreach ($categories as $category) {
            $records[] = [
                'id' => $category->id,
                'type' => 'categories',
                'name' => (string) $category->name,
                'status' => $category->is_active === false ? 'Inactive' : 'Active',
                'detail' => '/'.$category->slug,
                'link' => ['path' => '/admin/categories', 'query' => ['q' => (string) $category->name]],
            ];
        }

        foreach ($expenses as $expense) {
            $records[] = [
                'id' => $expense->id,
                'type' => 'expenses',
                'name' => (string) $expense->name,
                'status' => '',
                'detail' => ($expense->category ?: '—').' · AED '.number_format((float) $expense->amount, 2),
                'link' => ['path' => '/admin/accounting', 'query' => ['q' => (string) $expense->name]],
            ];
        }

        return $records;
    }

    /**
     * Admin sections whose name matches the term — returned even when no
     * records do, so "search anything" always leads somewhere useful.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sections(string $needle): array
    {
        $matches = [];

        foreach (self::SECTIONS as $path => $section) {
            $title = mb_strtolower($section['title']);
            $hit = str_contains($title, $needle)
                || (isset($section['terms']) && in_array($needle, $section['terms'], true));

            if ($hit) {
                $matches[] = ['title' => $section['title'], 'path' => $path];
            }
        }

        return $matches;
    }

    /** Escape a term for a case-insensitive LIKE, mirroring the list endpoints. */
    private function like(string $needle): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle).'%';
    }
}
