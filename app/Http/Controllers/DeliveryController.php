<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeliveryController extends Controller
{
    public function dashboard(): View
    {
        $total = DB::table('deliveries')->count();
        $review = DB::table('deliveries')->where('status', 'review')->count();
        $completed = DB::table('deliveries')->where('status', 'completed')->count();
        $deliveries = DB::table('deliveries')->latest()->limit(7)->get();

        return view('dashboard', compact('total', 'review', 'completed', 'deliveries'));
    }

    public function create(): View
    {
        return view('deliveries.upload');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $paths = [];
        $firstId = null;

        try {
            DB::transaction(function () use ($request, &$paths, &$firstId) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('deliveries', 'local');
                    if ($path === false) {
                        throw new \RuntimeException('The delivery photo could not be stored.');
                    }
                    $paths[] = $path;
                    $id = DB::table('deliveries')->insertGetId([
                        'photo_path' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'status' => 'review',
                        'items' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $firstId ??= $id;
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return redirect()->route('deliveries.edit', $firstId)
            ->with('success', 'Photos saved. Enter the receipt details, then review and approve.');
    }

    public function index(Request $request): View
    {
        $status = $request->query('status') === 'completed' ? 'completed' : 'review';
        $search = mb_substr((string) $request->query('search', ''), 0, 100);
        $deliveries = DB::table('deliveries')->where('status', $status)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_id', 'like', '%'.$search.'%')
                        ->orWhere('branch', 'like', '%'.$search.'%');
                });
            })->latest()->paginate(15)->withQueryString();

        return view('deliveries.index', compact('deliveries', 'status', 'search'));
    }

    private function delivery(int $id): object
    {
        $delivery = DB::table('deliveries')->find($id);
        abort_if($delivery === null, 404);
        $delivery->items = json_decode($delivery->items ?? '[]', true);

        return $delivery;
    }

    public function edit(int $delivery): View
    {
        return view('deliveries.review', ['delivery' => $this->delivery($delivery)]);
    }

    public function photo(int $delivery): BinaryFileResponse
    {
        $record = $this->delivery($delivery);
        abort_unless(Storage::disk('local')->exists($record->photo_path), 404);

        return response()->file(Storage::disk('local')->path($record->photo_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function update(Request $request, int $delivery): RedirectResponse
    {
        $this->delivery($delivery);
        $data = $request->validate([
            'reference_id' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['array:sku,item,quantity'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.item' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'action' => ['required', 'in:save,approve'],
        ]);
        $status = $data['action'] === 'approve' ? 'completed' : 'review';
        DB::table('deliveries')->where('id', $delivery)->update([
            'reference_id' => trim($data['reference_id']),
            'branch' => trim($data['branch']),
            'items' => json_encode(array_values($data['items'])),
            'status' => $status,
            'updated_at' => now(),
        ]);

        return redirect()->route('deliveries.index', ['status' => $status])
            ->with('success', $status === 'completed' ? 'Delivery approved and ready to export.' : 'Draft saved for review.');
    }

    private function exportRows(): array
    {
        $rows = [];
        foreach (DB::table('deliveries')->where('status', 'completed')->orderBy('id')->get() as $delivery) {
            foreach (json_decode($delivery->items ?? '[]', true) as $item) {
                $rows[] = [$delivery->reference_id, $delivery->branch, $item['sku'] ?? '', $item['item'], $item['quantity']];
            }
        }

        return $rows;
    }

    public function export(): View
    {
        return view('deliveries.export', ['rows' => $this->exportRows()]);
    }

    private function spreadsheetCell(mixed $value): string
    {
        $text = str_replace(["\t", "\r", "\n"], ' ', (string) $value);
        if (preg_match('/^\s*[=+@-]/u', $text)) {
            return "'".$text;
        }

        return $text;
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->exportRows();

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Reference ID', 'Branch', 'SKU', 'Item', 'Quantity'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->spreadsheetCell(...), $row), ',', '"', '');
            }
            fclose($stream);
        }, 'HTDeliveries-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
