<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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

        $branches = $this->branchSummary();

        return view('dashboard', compact('total', 'review', 'completed', 'deliveries', 'branches'));
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
            ->with('success', 'Photos saved. The reader will open on the review screen. Check all extracted details before approval.');
    }

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['all', 'completed'], true) ? $request->query('status') : 'review';
        $branch = mb_substr((string) $request->query('branch', ''), 0, 255);
        $unassigned = $request->boolean('unassigned');
        $branches = $this->branchSummary();
        $search = mb_substr((string) $request->query('search', ''), 0, 100);
        $deliveries = DB::table('deliveries')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($unassigned, fn ($query) => $query->whereRaw("TRIM(COALESCE(branch, '')) = ''"))
            ->when(!$unassigned && $branch !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(branch)) = LOWER(?)', [$branch]))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_id', 'like', '%'.$search.'%')
                        ->orWhere('branch', 'like', '%'.$search.'%');
                });
            })->latest()->paginate(15)->withQueryString();

        return view('deliveries.index', compact('deliveries', 'status', 'search', 'branches', 'branch', 'unassigned'));
    }

    private function branchSummary(): \Illuminate\Support\Collection
    {
        return DB::table('deliveries')
            ->selectRaw("MIN(TRIM(COALESCE(branch, ''))) AS branch, COUNT(*) AS total, SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) AS review, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->groupByRaw("LOWER(TRIM(COALESCE(branch, '')))")
            ->orderByDesc('total')
            ->get();
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

    public function read(Request $request, int $delivery): JsonResponse
    {
        $record = $this->delivery($delivery);
        if ($record->status === 'completed') {
            return response()->json(['message' => 'Save this delivery for review before reading it again.'], 409);
        }
        $result = $request->validate([
            'raw_text' => ['required', 'string', 'max:100000'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'warnings' => ['present', 'array', 'max:200'],
            'warnings.*' => ['string', 'max:2000'],
            'items' => ['present', 'array', 'max:200'],
            'items.*.line_number' => ['nullable', 'string', 'max:255'],
            'items.*.item_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.total_order' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.item' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::table('deliveries')->where('id', $delivery)->update([
            'reading_result' => json_encode($result), 'updated_at' => now(),
        ]);

        return response()->json($result);
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
            'items.*' => ['array:sku,item,quantity,ordered_quantity,line_number,item_quantity,total_order'],
            'items.*.line_number' => ['nullable', 'string', 'max:255'],
            'items.*.item_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.total_order' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.item' => ['required', 'string', 'max:255'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'action' => ['required', 'in:save,approve'],
            'raw_text' => ['nullable', 'string', 'max:100000'],
        ]);
        $status = $data['action'] === 'approve' ? 'completed' : 'review';
        $reading = json_decode($this->delivery($delivery)->reading_result ?? 'null', true);
        if (array_key_exists('raw_text', $data)) {
            $reading ??= ['warnings' => [], 'items' => []];
            $reading['raw_text'] = $data['raw_text'] ?? '';
        }
        DB::table('deliveries')->where('id', $delivery)->update([
            'reference_id' => trim($data['reference_id']),
            'branch' => trim($data['branch']),
            'items' => json_encode(array_values($data['items'])),
            'status' => $status,
            'reading_result' => $reading === null ? null : json_encode($reading),
            'updated_at' => now(),
        ]);

        return redirect()->route('deliveries.index', ['status' => $status])
            ->with('success', $status === 'completed' ? 'Delivery approved and ready to export.' : 'Draft saved for review.');
    }

    private function exportRows(string $branch = ''): array
    {
        $rows = [];
        foreach (DB::table('deliveries')->where('status', 'completed')->when($branch !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(branch)) = LOWER(?)', [$branch]))->orderBy('id')->get() as $delivery) {
            foreach (json_decode($delivery->items ?? '[]', true) as $index => $item) {
                $rows[] = [$delivery->reference_id, trim($delivery->branch ?? ''), $item['line_number'] ?? $index + 1, $item['item'], $item['sku'] ?? '', $item['item_quantity'] ?? $item['ordered_quantity'] ?? '', $item['total_order'] ?? '', $item['quantity']];
            }
        }

        return $rows;
    }

    public function export(Request $request): View
    {
        return view('deliveries.export', ['rows' => $this->exportRows((string) $request->query('branch', '')), 'branch' => (string) $request->query('branch', ''), 'branches' => $this->branchSummary()]);
    }

    private function spreadsheetCell(mixed $value): string
    {
        $text = str_replace(["\t", "\r", "\n"], ' ', (string) $value);
        if (preg_match('/^\s*[=+@-]/u', $text)) {
            return "'".$text;
        }

        return $text;
    }

    public function workbook(Request $request): BinaryFileResponse
    {
        abort_unless(class_exists(\ZipArchive::class), 503, 'Enable the PHP zip extension in Herd to download Excel workbooks.');
        $branch = (string) $request->query('branch', '');
        $rows = $this->exportRows($branch);
        $groups = collect($rows)->groupBy(fn ($row) => mb_strtolower($row[1]));
        $summary = [['Branch', 'Approved deliveries', 'Item rows', 'Share of approved deliveries (%)']];
        $counts = DB::table('deliveries')->where('status', 'completed')
            ->when($branch !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(branch)) = LOWER(?)', [$branch]))
            ->selectRaw("MIN(TRIM(COALESCE(branch, ''))) AS branch, COUNT(*) AS total")
            ->groupByRaw("LOWER(TRIM(COALESCE(branch, '')))")->get();
        $total = $counts->sum('total');
        foreach ($counts as $count) {
            $summary[] = [$count->branch ?: 'Branch not assigned', $count->total, count($groups->get(mb_strtolower($count->branch), [])), $total ? round($count->total / $total * 100, 1) : 0];
        }
        $sheets = ['All Summary' => $summary];
        $headers = ['Reference ID', 'Branch', 'No.', 'FS Item', 'Item SKU', 'Item Quantity', 'Total Order', 'Received Order'];
        foreach ($groups as $group) {
            $name = preg_replace('/[\\\\\/\?\*\[\]:]/u', '-', $group->first()[1] ?: 'Branch not assigned');
            $name = trim(mb_substr($name, 0, 31), " '") ?: 'Branch';
            $base = $name;
            $suffix = 2;
            while (in_array(mb_strtolower($name), array_map('mb_strtolower', array_keys($sheets)), true)) {
                $name = mb_substr($base, 0, 26).' ('.$suffix++.')';
            }
            $sheets[$name] = array_merge([$headers], $group->all());
        }
        $path = tempnam(sys_get_temp_dir(), 'ht-export-');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            abort(500, 'Could not create the workbook.');
        }
        $xml = fn ($value) => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $id = 0;
        foreach ($sheets as $name => $sheetRows) {
            $id++;
            $book .= '<sheet name="'.$xml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="8" width="24" customWidth="1"/></cols><sheetData>';
            foreach ($sheetRows as $rowIndex => $row) {
                $sheet .= '<row r="'.($rowIndex + 1).'">';
                foreach ($row as $column => $value) {
                    $cell = chr(65 + $column).($rowIndex + 1);
                    $sheet .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';
                }
                $sheet .= '</row>';
            }
            $sheet .= '</sheetData><autoFilter ref="A1:'.chr(64 + count($sheetRows[0])).max(1, count($sheetRows)).'"/></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $sheet);
        }
        $zip->addFromString('[Content_Types].xml', $types.'</Types>');
        $zip->addFromString('xl/workbook.xml', $book.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->close();

        return response()->download($path, 'HTDeliveries-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->exportRows((string) $request->query('branch', ''));

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Reference ID', 'Branch', 'No.', 'FS Item', 'Item SKU', 'Item Quantity', 'Total Order', 'Received Order'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->spreadsheetCell(...), $row), ',', '"', '');
            }
            fclose($stream);
        }, 'HTDeliveries-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
