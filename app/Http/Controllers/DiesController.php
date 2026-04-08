<?php
namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DiesController extends Controller
{
    public function index(Request $request)
    {
        $query = Dies::withCount(['preventives', 'correctives'])
            ->with('processes');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('no_part',   'like', '%' . $request->search . '%')
                  ->orWhere('nama_dies','like', '%' . $request->search . '%')
                  ->orWhere('id_sap',  'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('line')) {
            $query->where('line', $request->line);
        }

        $dies          = $query->orderBy('no_part')->paginate(20)->withQueryString();
        $lines         = Dies::distinct()->orderBy('line')->pluck('line');
        $totalProcesses = DiesProcess::count();

        return Inertia::render('Dies/Index', [
            'dies'            => $dies,
            'lines'           => $lines,
            'totalProcesses'  => $totalProcesses,
            'filters'         => $request->only('search', 'status', 'line'),
            'bstb_preview'    => session('bstb_preview',   []),
            'bstb_not_found'  => session('bstb_not_found', []),
            'bstb_no_change'  => session('bstb_no_change', 0),
            'bstb_total'      => session('bstb_total',     0),
        ]);
    }

    public function show(string $idSap)
    {
        $dies = Dies::with(['processes'])->findOrFail($idSap);

        $preventives = $dies->preventives()
            ->with('process')
            ->latest('report_date')
            ->paginate(10, ['*'], 'pm_page');

        $correctives = $dies->correctives()
            ->latest('report_date')
            ->paginate(10, ['*'], 'cm_page');

        return Inertia::render('Dies/Show', [
            'dies'        => $dies,
            'preventives' => $preventives,
            'correctives' => $correctives,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_sap'           => 'required|string|unique:dies,id_sap',
            'no_part'          => 'required|string',
            'nama_dies'        => 'required|string',
            'line'             => 'required|string',
            'kategori'         => 'nullable|string',
            'status'           => 'required|in:active,slow_moving,discontinue,ohp,service_part',
            'is_common'        => 'boolean',
            'forecast_per_day' => 'required|integer|min:0',
            'process_name'     => 'nullable|string',
            'std_stroke'       => 'nullable|integer|min:0',
            'tonase'           => 'nullable|numeric|min:0',
            'last_mtc_date'    => 'nullable|date',
        ]);

        $dies = Dies::create($request->only([
            'id_sap','no_part','nama_dies','line','kategori',
            'status','is_common','forecast_per_day',
        ]));

        if ($request->filled('process_name')) {
            $dies->processes()->create([
                'process_name'   => $request->process_name,
                'no_proses'      => 0,
                'std_stroke'     => $request->std_stroke ?? 20000,
                'current_stroke' => 0,
                'tonase'         => $request->tonase ?: null,
                'last_mtc_date'  => $request->last_mtc_date ?: null,
            ]);
        }

        return redirect()->route('dies.index')->with('success', 'Data dies berhasil ditambahkan.');
    }

    public function update(Request $request, string $idSap)
    {
        $dies = Dies::findOrFail($idSap);

        $request->validate([
            'no_part'          => 'required|string',
            'nama_dies'        => 'required|string',
            'line'             => 'required|string',
            'kategori'         => 'nullable|string',
            'status'           => 'required|in:active,slow_moving,discontinue,ohp,service_part',
            'is_common'        => 'boolean',
            'forecast_per_day' => 'required|integer|min:0',
        ]);

        $dies->update($request->only([
            'no_part','nama_dies','line','kategori',
            'status','is_common','forecast_per_day',
        ]));

        return redirect()->route('dies.index')->with('success', 'Data dies berhasil diperbarui.');
    }

    public function destroy(string $idSap)
    {
        Dies::findOrFail($idSap)->delete();
        return redirect()->route('dies.index')->with('success', 'Data dies berhasil dihapus.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:5120',
        ]);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
        $sheet       = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $updated = 0;
        $skipped = 0;

        foreach (array_slice($sheet, 2) as $row) {
            $idSap       = trim((string)($row['L'] ?? ''));
            $totalStroke = intval($row['AC'] ?? 0);

            if (empty($idSap)) { $skipped++; continue; }

            $dies = Dies::where('id_sap', $idSap)->first();
            if (!$dies) { $skipped++; continue; }

            $dies->update(['total_stroke' => $totalStroke]);
            $updated++;
        }

        return redirect()->route('dies.index')
            ->with('success', "Import selesai. {$updated} dies diperbarui, {$skipped} dilewati.");
    }

    public function importBstbPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $parsed = $this->parseBstbCsv($request->file('file')->getPathname());
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()]);
        }

        if (empty($parsed)) {
            return back()->withErrors(['file' => 'Tidak ada data valid ditemukan di file CSV.']);
        }

        $idSaps       = collect($parsed)->pluck('id_sap')->unique()->filter()->values();
        $existingDies = Dies::whereIn('id_sap', $idSaps)
            ->with('processes')
            ->get()
            ->keyBy('id_sap');

        $preview  = [];
        $notFound = [];
        $noChange = 0;

        foreach ($parsed as $row) {
            $idSap = $row['id_sap'];
            if (!$idSap) continue;

            if (!$existingDies->has($idSap)) {
                $notFound[] = ['id_sap' => $idSap, 'desc' => $row['desc']];
                continue;
            }

            $dies   = $existingDies[$idSap];
            $addQty = $row['qty'];

            if ($addQty <= 0) { $noChange++; continue; }

            $preview[] = [
                'id_sap'    => $idSap,
                'no_part'   => $dies->no_part,
                'nama_dies' => $dies->nama_dies,
                'line'      => $dies->line,
                'desc'      => $row['desc'],
                'qty'       => $addQty,
                'processes' => $dies->processes->map(fn($pr) => [
                    'id'           => $pr->id,
                    'process_name' => $pr->process_name,
                    'old_stroke'   => $pr->current_stroke,
                    'add_qty'      => $addQty,
                    'new_stroke'   => $pr->current_stroke + $addQty,
                    'std_stroke'   => $pr->std_stroke,
                ])->values()->toArray(),
            ];
        }

        session([
            'bstb_preview'   => $preview,
            'bstb_not_found' => array_unique($notFound, SORT_REGULAR),
            'bstb_no_change' => $noChange,
            'bstb_total'     => count($parsed),
        ]);

        $dies           = Dies::withCount(['preventives','correctives'])->with('processes')->orderBy('no_part')->paginate(20)->withQueryString();
        $lines          = Dies::distinct()->orderBy('line')->pluck('line');
        $totalProcesses = DiesProcess::count();

        return Inertia::render('Dies/Index', [
            'dies'           => $dies,
            'lines'          => $lines,
            'totalProcesses' => $totalProcesses,
            'filters'        => request()->only('search', 'status', 'line'),
            'bstb_preview'   => $preview,
            'bstb_not_found' => array_slice(array_unique($notFound, SORT_REGULAR), 0, 20),
            'bstb_no_change' => $noChange,
            'bstb_total'     => count($parsed),
        ]);
    }

    public function importBstbConfirm(Request $request)
    {
        $request->validate([
            'updates'          => 'required|array',
            'updates.*.id_sap' => 'required|string',
            'updates.*.processes' => 'required|array',
            'updates.*.processes.*.id'      => 'required|integer',
            'updates.*.processes.*.add_qty' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->updates as $item) {
                foreach ($item['processes'] as $proc) {
                    if ($proc['add_qty'] <= 0) continue;
                    DiesProcess::where('id', $proc['id'])->increment('current_stroke', $proc['add_qty']);
                }
                Dies::where('id_sap', $item['id_sap'])->update([
                    'bstb_updated_at' => now(),
                ]);
            }
        });

        session()->forget(['bstb_preview', 'bstb_not_found', 'bstb_no_change', 'bstb_total']);

        $updated        = count($request->updates);
        $dies           = Dies::withCount(['preventives','correctives'])->with('processes')->orderBy('no_part')->paginate(20)->withQueryString();
        $lines          = Dies::distinct()->orderBy('line')->pluck('line');
        $totalProcesses = DiesProcess::count();

        return Inertia::render('Dies/Index', [
            'dies'           => $dies,
            'lines'          => $lines,
            'totalProcesses' => $totalProcesses,
            'filters'        => request()->only('search', 'status', 'line'),
            'bstb_preview'   => [],
            'bstb_not_found' => [],
            'bstb_no_change' => 0,
            'bstb_total'     => 0,
            'flash'          => ['success' => "Berhasil update {$updated} dies dari BSTB."],
        ]);
    }

    private function parseBstbCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) throw new \Exception('Tidak dapat membuka file.');

        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
        }
        fclose($handle);

        $dataLines    = [];
        $currentShift = false;
        $headerPassed = 0;

        foreach ($lines as $line) {
            $upper = strtoupper(trim($line));

            if (
                str_contains($upper, 'SHIFT PAGI') ||
                str_contains($upper, 'SHIFT MALAM (SHIFT 2)') || str_contains($upper, 'SHIFT 2)') ||
                str_contains($upper, 'SHIFT MALAM (SHIFT 3)') || str_contains($upper, 'SHIFT 3)') ||
                (str_contains($upper, 'SHIFT 1') && !str_contains($upper, 'SHIFT 2') && !str_contains($upper, 'SHIFT 3'))
            ) {
                $currentShift = true;
                $headerPassed = 0;
                continue;
            }

            if (!$currentShift) continue;

            if ($headerPassed < 2) { $headerPassed++; continue; }

            $dataLines[] = $line;
        }

        $materialCol = 4;
        $descCol     = 5;

        foreach (array_slice($dataLines, 0, 50) as $line) {
            $cols = $this->parseCsvLine($line);
            foreach ($cols as $j => $cell) {
                $cell = trim($cell);
                if (strlen($cell) >= 8 && preg_match('/^[A-Z0-9]{8,}$/i', $cell)) {
                    $materialCol = $j;
                    $descCol     = $j + 1;
                    break 2;
                }
            }
        }

        $qtyScores = [];
        foreach (array_slice($dataLines, 0, 30) as $line) {
            $cols  = $this->parseCsvLine($line);
            $idSap = trim($cols[$materialCol] ?? '');
            if (!$idSap || strlen($idSap) < 8 || !preg_match('/^[A-Z0-9]/i', $idSap)) continue;

            foreach ($cols as $j => $cell) {
                if ($j <= $materialCol + 1) continue;
                $val = trim(str_replace([',', '"', ' '], '', $cell));
                if ($val === '' || str_contains($val, ':') || str_contains($val, '/') || str_contains($val, '.')) continue;
                if (is_numeric($val) && (int)$val >= 0) {
                    $qtyScores[$j] = ($qtyScores[$j] ?? 0) + 1;
                }
            }
        }

        $qtyCol = 14;
        if (!empty($qtyScores)) {
            arsort($qtyScores);
            $qtyCol = array_key_first($qtyScores);
        }

        $byMaterial = [];

        foreach ($dataLines as $line) {
            $cols = $this->parseCsvLine($line);
            if (count($cols) <= $qtyCol) continue;

            $idSap = trim($cols[$materialCol] ?? '');
            if (!$idSap || strlen($idSap) < 5) continue;

            $lower = strtolower($idSap);
            if (in_array($lower, ['material number', 'material', 'order', 'part name', 'material description'])) continue;
            if (!preg_match('/^[A-Z0-9]/i', $idSap)) continue;

            $qty = (int) preg_replace('/[^0-9]/', '', trim($cols[$qtyCol] ?? '0'));
            if ($qty <= 0) continue;

            if (!isset($byMaterial[$idSap])) {
                $byMaterial[$idSap] = [
                    'id_sap'  => $idSap,
                    'desc'    => trim($cols[$descCol] ?? ''),
                    'no_part' => $materialCol > 0 ? trim($cols[$materialCol - 1] ?? '') : '',
                    'qty'     => 0,
                ];
            }

            $byMaterial[$idSap]['qty'] += $qty;
        }

        return array_values($byMaterial);
    }

    private function parseCsvLine(string $line): array
    {
        $cols    = [];
        $current = '';
        $inQuote = false;

        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            if ($char === '"') {
                $inQuote = !$inQuote;
            } elseif ($char === ',' && !$inQuote) {
                $cols[]  = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $cols[] = trim($current);
        return $cols;
    }
}
