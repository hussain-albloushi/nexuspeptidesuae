<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'pending', 'blocked'];

    private function query(Request $request): Builder
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'segment' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
            'sort' => ['nullable', Rule::in(['name', 'email', 'phone', 'customer_status', 'created_at', 'last_activity_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
            'ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $query = User::where('role', 'customer');
        $pattern = fn (string $term): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))).'%';
        if (filled($data['q'] ?? null)) {
            $search = $pattern($data['q']);
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("LOWER(phone) LIKE ? ESCAPE '!'", [$search]);
            });
        }
        foreach (['status' => 'customer_status', 'segment' => 'segment'] as $input => $column) {
            if (filled($data[$input] ?? null)) {
                $query->where($column, $data[$input]);
            }
        }
        if (filled($data['location'] ?? null)) {
            $query->whereRaw("LOWER(location) LIKE ? ESCAPE '!'", [$pattern($data['location'])]);
        }
        if (isset($data['start_date'])) {
            $query->whereDate('created_at', '>=', $data['start_date']);
        }
        if (isset($data['end_date'])) {
            $query->whereDate('created_at', '<=', $data['end_date']);
        }
        if (isset($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        return $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')->orderBy('id');
    }

    public function index(Request $request): Collection|LengthAwarePaginator
    {
        $query = $this->query($request);

        return $request->filled('per_page') ? $query->paginate((int) $request->input('per_page'))->withQueryString() : $query->limit(200)->get();
    }

    private function customer(int $id): User
    {
        return User::where('role', 'customer')->findOrFail($id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $request->validate(['order_page' => ['nullable', 'integer', 'min:1'], 'activity_page' => ['nullable', 'integer', 'min:1']]);

        return response()->json([
            'customer' => $this->customer($id)->makeVisible('customer_notes'),
            'orders' => Order::where('user_id', $id)->latest()->paginate(10, ['id', 'order_number', 'status', 'total_amount', 'created_at'], 'order_page'),
            'activities' => DB::table('customer_activities')->where('customer_id', $id)->orderByDesc('created_at')->orderByDesc('id')->paginate(15, ['*'], 'activity_page'),
            'order_count' => Order::where('user_id', $id)->count(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[+0-9().\\s\\-]+$/'],
            'customer_status' => ['required', Rule::in(self::STATUSES)],
            'segment' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:10000'],
            'password' => [$customer ? 'nullable' : 'required', 'string', 'min:8', 'max:128'],
        ]);
    }

    private function record(Request $request, User $customer, string $description): void
    {
        DB::table('customer_activities')->insert(['customer_id' => $customer->id, 'description' => $description, 'actor_name' => $request->user()->name, 'created_at' => now()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $customer = DB::transaction(function () use ($request, $data): User {
            $customer = new User;
            $customer->forceFill([...$data, 'role' => 'customer'])->save();
            $this->record($request, $customer, 'Customer account created.');

            return $customer;
        });

        return response()->json(['message' => 'Customer created successfully.', 'customer' => $customer], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($id);
        $data = $this->validated($request, $customer);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($request, $customer, $data): void {
            $oldStatus = $customer->customer_status;
            $emailChanged = $customer->email !== $data['email'];
            $customer->forceFill($data);
            if ($emailChanged) {
                $customer->email_verified_at = null;
            }
            $customer->save();
            if ($customer->customer_status !== 'active' || $emailChanged || isset($data['password'])) {
                $customer->tokens()->delete();
                DB::table('sessions')->where('user_id', $customer->id)->delete();
            }
            $this->record($request, $customer, $oldStatus !== $customer->customer_status ? 'Status changed from '.$oldStatus.' to '.$customer->customer_status.'.' : 'Customer profile updated.');
        });

        return response()->json(['message' => 'Customer updated successfully.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Bypasses the bulk confirmation dialog by design; the admin-only
        // route is the explicit caller's confirmation.
        $request->merge(['ids' => [$id], 'action' => 'delete', 'confirmed' => '1']);

        return $this->bulk($request);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['delete', 'status'])],
            'status' => ['required_if:action,status', Rule::in(self::STATUSES)],
            'confirmed' => ['exclude_unless:action,delete', 'required', 'accepted'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $customers = User::where('role', 'customer')->whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            abort_if($customers->count() !== count($data['ids']), 422, 'A selected customer is no longer available. Refresh and try again.');
            foreach ($customers as $customer) {
                if ($data['action'] === 'delete') {
                    $this->record($request, $customer, 'Customer deleted ('.$customer->name.' · '.$customer->email.').');
                    $customer->tokens()->delete();
                    DB::table('sessions')->where('user_id', $customer->id)->delete();
                    DB::table('password_reset_tokens')->where('email', $customer->email)->delete();
                    $customer->forceDelete();

                    continue;
                }

                $oldStatus = $customer->customer_status;
                $customer->customer_status = $data['status'];
                $customer->save();
                $this->record($request, $customer, 'Status changed from '.$oldStatus.' to '.$data['status'].'.');
                if ($data['status'] !== 'active') {
                    $customer->tokens()->delete();
                    DB::table('sessions')->where('user_id', $customer->id)->delete();
                }
            }
        });

        return response()->json(['message' => count($data['ids']).' customer(s) '.($data['action'] === 'delete' ? 'deleted. Order records are preserved.' : 'updated successfully.')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate(['format' => ['required', Rule::in(['csv', 'excel'])]]);
        $query = $this->query($request);
        $excel = $request->input('format') === 'excel';

        return response()->streamDownload(function () use ($query, $excel): void {
            $stream = fopen('php://output', 'w');
            $write = function (array $row) use ($stream, $excel): void {
                if ($excel) {
                    echo '<Row>';
                    foreach ($row as $value) {
                        echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
                    }
                    echo '</Row>';
                } else {
                    fputcsv($stream, array_map(fn ($value) => preg_match('/^[\\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $row), ',', '"', '');
                }
            };
            if ($excel) {
                echo '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Customers"><Table>';
            } else {
                fwrite($stream, "\xEF\xBB\xBF");
            }
            $write(['ID', 'Name', 'Email', 'Phone', 'Status', 'Segment', 'Location', 'Registered', 'Last activity']);
            foreach ($query->lazy(500) as $customer) {
                $write([$customer->id, $customer->name, $customer->email, $customer->phone, $customer->customer_status, $customer->segment, $customer->location, $customer->created_at?->toIso8601String(), $customer->last_activity_at?->toIso8601String()]);
            }
            if ($excel) {
                echo '</Table></Worksheet></Workbook>';
            }
            fclose($stream);
        }, 'customers-'.now()->format('Y-m-d').($excel ? '.xml' : '.csv'), ['Content-Type' => $excel ? 'application/xml; charset=UTF-8' : 'text/csv; charset=UTF-8']);
    }
}
