<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesCorrective;
use App\Models\DiesCorrectiveRepairSession;
use App\Models\DiesHistorySparepart;
use App\Models\DiesIo;
use App\Models\DiesProcess;
use App\Models\DiesSparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DiesCorrectiveController extends Controller
{
    public function index(Request $request)
    {
        $year      = $request->year ?? now()->year;
        $dateFrom  = $request->date_from;
        $dateTo    = $request->date_to;
        $useDateRange = $dateFrom || $dateTo;

        $baseQuery = function ($q) use ($request, $year, $dateFrom, $dateTo, $useDateRange) {
            $q->when($request->status, fn($q) => $q->where('status', $request->status))
              ->when($request->dies_id, fn($q) => $q->where('dies_id', $request->dies_id))
              ->when($request->line, fn($q) => $q->whereHas('dies', fn($d) => $d->where('line', $request->line)))
              ->when($useDateRange, function ($q) use ($dateFrom, $dateTo) {
                  if ($dateFrom) $q->whereDate('report_date', '>=', $dateFrom);
                  if ($dateTo)   $q->whereDate('report_date', '<=', $dateTo);
              })
              ->when(!$useDateRange && $request->month, fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
              ->when(!$useDateRange && !$request->month, fn($q) => $q->whereYear('report_date', $year))
              ->when($request->search, fn($q) => $q->where(function ($sq) use ($request) {
                  $sq->where('report_no', 'like', '%' . $request->search . '%')
                     ->orWhere('pic_name', 'like', '%' . $request->search . '%')
                     ->orWhereHas('dies', fn($d) => $d->where('no_part', 'like', '%' . $request->search . '%'));
              }));
        };

        $query = DiesCorrective::with([
            'dies',
            'process',
            'spareparts.sparepart',
            'createdBy',
            'closedBy',
            'repairSessions',
        ])->tap($baseQuery)->latest('report_date');

        $paginated = $query->paginate(20)->withQueryString();

        $aggQuery = DiesCorrective::tap($baseQuery);

        $agg = $aggQuery->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as count_in_progress,
            SUM(CASE WHEN status = "on_repair" THEN 1 ELSE 0 END) as count_on_repair,
            SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as count_closed,
            SUM(CASE WHEN off_machine_at IS NOT NULL THEN machine_duration_minutes ELSE 0 END) as total_machine_minutes,
            SUM(repair_duration_minutes) as total_repair_minutes,
            AVG(CASE WHEN off_machine_at IS NOT NULL AND machine_duration_minutes IS NOT NULL THEN machine_duration_minutes END) as avg_machine_minutes,
            AVG(CASE WHEN repair_duration_minutes IS NOT NULL THEN repair_duration_minutes END) as avg_repair_minutes,
            SUM(CASE WHEN off_machine_at IS NULL THEN 0 ELSE 1 END) as count_off_machine,
            SUM(CASE WHEN off_machine_at IS NOT NULL THEN 0 ELSE 1 END) as count_in_machine
        ')->first();

        $summary = [
            'open'                 => DiesCorrective::where('status', 'open')->whereYear('report_date', $year)->count(),
            'in_progress'          => DiesCorrective::where('status', 'in_progress')->whereYear('report_date', $year)->count(),
            'on_repair'            => DiesCorrective::where('status', 'on_repair')->whereYear('report_date', $year)->count(),
            'closed'               => DiesCorrective::where('status', 'closed')->whereYear('report_date', $year)->count(),
            'total_machine_minutes'=> (int) ($agg->total_machine_minutes ?? 0),
            'total_repair_minutes' => (int) ($agg->total_repair_minutes ?? 0),
            'avg_machine_minutes'  => (int) round($agg->avg_machine_minutes ?? 0),
            'avg_repair_minutes'   => (int) round($agg->avg_repair_minutes ?? 0),
            'count_off_machine'    => (int) ($agg->count_off_machine ?? 0),
            'count_in_machine'     => (int) ($agg->count_in_machine ?? 0),
        ];

        $diesList = Dies::with('processes')
            ->orderBy('no_part')
            ->get(['id_sap', 'no_part', 'nama_dies', 'line']);

        $spareparts = DiesSparepart::orderBy('sparepart_name')
            ->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);

        $ios = DiesIo::orderBy('nama')
            ->get(['id', 'nama', 'cc', 'io_number']);

        $lines = Dies::distinct()
            ->orderBy('line')
            ->whereNotNull('line')
            ->where('line', '!=', '')
            ->pluck('line')
            ->values();

        return Inertia::render('Dies/Corrective/Index', [
            'correctives' => [
                'data'  => $paginated->items(),
                'links' => $paginated->linkCollection()->toArray(),
                'meta'  => [
                    'current_page' => $paginated->currentPage(),
                    'from'         => $paginated->firstItem(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'to'           => $paginated->lastItem(),
                    'total'        => $paginated->total(),
                    'links'        => $paginated->linkCollection()->toArray(),
                ],
            ],
            'diesList'    => $diesList,
            'spareparts'  => $spareparts,
            'ios'         => $ios,
            'lines'       => $lines,
            'summary'     => $summary,
            'filters'     => $request->only('search', 'status', 'dies_id', 'line', 'month', 'year', 'date_from', 'date_to') + ['year' => (string) $year],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dies_id'                   => 'required|exists:dies,id_sap',
            'process_id'                => 'required|exists:dies_processes,id',
            'stroke_at_maintenance'     => 'nullable|integer|min:0',
            'problem_description'       => 'nullable|string',
            'cause'                     => 'nullable|string',
            'repair_action'             => 'nullable|string',
            'photos_before'             => 'nullable|array',
            'photos_before.*'           => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'photos_after'              => 'nullable|array',
            'photos_after.*'            => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.io_id'        => 'required_with:spareparts.*.sparepart_id|exists:dies_io,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $photos = [];

            if ($request->hasFile('photos_before')) {
                foreach ($request->file('photos_before') as $photo) {
                    $photos[] = ['path' => $photo->store('dies/corrective', 'public'), 'type' => 'before'];
                }
            }
            if ($request->hasFile('photos_after')) {
                foreach ($request->file('photos_after') as $photo) {
                    $photos[] = ['path' => $photo->store('dies/corrective', 'public'), 'type' => 'after'];
                }
            }

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            $process = DiesProcess::find($request->process_id);

            $corrective = DiesCorrective::create([
                'report_no'               => "CM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'                 => $request->dies_id,
                'process_id'              => $request->process_id,
                'pic_name'                => Auth::user()->name,
                'report_date'             => now(),
                'stroke_at_maintenance'   => $request->stroke_at_maintenance ?? $process?->current_stroke ?? 0,
                'repair_duration_minutes' => null,
                'machine_duration_minutes'=> null,
                'problem_description'     => $request->problem_description,
                'cause'                   => $request->cause,
                'repair_action'           => $request->repair_action,
                'photos'                  => $photos ?: null,
                'status'                  => 'in_progress',
                'created_by'              => Auth::id(),
            ]);

            if ($request->has('spareparts') && is_array($request->spareparts)) {
                foreach ($request->spareparts as $sp) {
                    if (empty($sp['sparepart_id'])) continue;
                    $sparepart = DiesSparepart::findOrFail($sp['sparepart_id']);
                    $qty = (int) $sp['quantity'];
                    if ($sparepart->stok < $qty) throw new \Exception("Stok {$sparepart->sparepart_name} tidak mencukupi.");
                    DiesHistorySparepart::create([
                        'tipe'           => 'corrective',
                        'maintenance_id' => $corrective->id,
                        'sparepart_id'   => $sp['sparepart_id'],
                        'io_id'          => $sp['io_id'],
                        'quantity'       => $qty,
                        'notes'          => $sp['notes'] ?? null,
                        'created_by'     => Auth::id(),
                    ]);
                    $sparepart->decrement('stok', $qty);
                }
            }
        });

        return back()->with('success', 'Laporan corrective berhasil dibuat.');
    }

    public function update(Request $request, DiesCorrective $diesCorrective)
    {
        $request->validate([
            'problem_description'       => 'nullable|string',
            'cause'                     => 'nullable|string',
            'repair_action'             => 'nullable|string',
            'photos_before'             => 'nullable|array',
            'photos_before.*'           => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'photos_after'              => 'nullable|array',
            'photos_after.*'            => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.io_id'        => 'required_with:spareparts.*.sparepart_id|exists:dies_io,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesCorrective) {
            $photos = $diesCorrective->photos ?? [];

            if ($request->hasFile('photos_before')) {
                foreach ($request->file('photos_before') as $photo) {
                    $photos[] = ['path' => $photo->store('dies/corrective', 'public'), 'type' => 'before'];
                }
            }
            if ($request->hasFile('photos_after')) {
                foreach ($request->file('photos_after') as $photo) {
                    $photos[] = ['path' => $photo->store('dies/corrective', 'public'), 'type' => 'after'];
                }
            }

            $diesCorrective->update([
                'problem_description' => $request->problem_description,
                'cause'               => $request->cause,
                'repair_action'       => $request->repair_action,
                'photos'              => $photos ?: null,
            ]);

            if ($request->has('spareparts') && is_array($request->spareparts)) {
                foreach ($request->spareparts as $sp) {
                    if (empty($sp['sparepart_id'])) continue;
                    $sparepart = DiesSparepart::findOrFail($sp['sparepart_id']);
                    $qty = (int) $sp['quantity'];
                    if ($sparepart->stok < $qty) throw new \Exception("Stok {$sparepart->sparepart_name} tidak mencukupi.");
                    DiesHistorySparepart::create([
                        'tipe'           => 'corrective',
                        'maintenance_id' => $diesCorrective->id,
                        'sparepart_id'   => $sp['sparepart_id'],
                        'io_id'          => $sp['io_id'],
                        'quantity'       => $qty,
                        'notes'          => $sp['notes'] ?? null,
                        'created_by'     => Auth::id(),
                    ]);
                    $sparepart->decrement('stok', $qty);
                }
            }
        });

        return back()->with('success', 'Laporan corrective berhasil diperbarui.');
    }

    public function offMachine(Request $request, DiesCorrective $diesCorrective)
    {
        $offAt = now();
        $reportDate = $diesCorrective->report_date instanceof \Carbon\Carbon
            ? $diesCorrective->report_date
            : \Carbon\Carbon::parse($diesCorrective->report_date);

        $machineDuration = (int) $reportDate->diffInMinutes($offAt);

        $diesCorrective->update([
            'status'                   => 'on_repair',
            'off_machine_at'           => $offAt,
            'machine_duration_minutes' => $machineDuration,
        ]);

        return back()->with('success', 'Dies berhasil dicatat turun dari mesin.');
    }

    public function repairStart(Request $request, DiesCorrective $diesCorrective)
    {
        if ($diesCorrective->status !== 'on_repair') {
            return back()->with('error', 'Status tidak valid untuk memulai repair.');
        }

        $activeSession = $diesCorrective->repairSessions()->whereNull('ended_at')->first();
        if ($activeSession) {
            return back()->with('error', 'Sesi repair sedang berjalan.');
        }

        DiesCorrectiveRepairSession::create([
            'corrective_id' => $diesCorrective->id,
            'started_at'    => now(),
            'ended_at'      => null,
            'created_by'    => Auth::id(),
        ]);

        $diesCorrective->update(['repair_started_at' => now()]);

        return back()->with('success', 'Repair dimulai.');
    }

    public function repairPause(Request $request, DiesCorrective $diesCorrective)
    {
        if ($diesCorrective->status !== 'on_repair') {
            return back()->with('error', 'Status tidak valid.');
        }

        $activeSession = $diesCorrective->repairSessions()->whereNull('ended_at')->first();
        if (!$activeSession) {
            return back()->with('error', 'Tidak ada sesi repair yang berjalan.');
        }

        $endedAt = now();
        $duration = (int) $activeSession->started_at->diffInMinutes($endedAt);

        $activeSession->update([
            'ended_at'         => $endedAt,
            'duration_minutes' => $duration,
        ]);

        $totalMinutes = $diesCorrective->repairSessions()
            ->whereNotNull('ended_at')
            ->sum('duration_minutes');

        $diesCorrective->update(['repair_duration_minutes' => $totalMinutes]);

        return back()->with('success', 'Repair dijeda.');
    }

    public function close(Request $request, DiesCorrective $diesCorrective)
    {
        $request->validate(['action' => 'nullable|string']);

        $activeSession = $diesCorrective->repairSessions()->whereNull('ended_at')->first();
        if ($activeSession) {
            $endedAt  = now();
            $duration = (int) $activeSession->started_at->diffInMinutes($endedAt);
            $activeSession->update(['ended_at' => $endedAt, 'duration_minutes' => $duration]);
        }

        $repairMinutes = $diesCorrective->repairSessions()
            ->whereNotNull('ended_at')
            ->sum('duration_minutes');

        $closedAt = now();
        $machineDurationMinutes = $diesCorrective->machine_duration_minutes;
        $offMachineAt = $diesCorrective->off_machine_at;

        if (!$offMachineAt) {
            $reportDate = $diesCorrective->report_date instanceof \Carbon\Carbon
                ? $diesCorrective->report_date
                : \Carbon\Carbon::parse($diesCorrective->report_date);
            $machineDurationMinutes = (int) $reportDate->diffInMinutes($closedAt);
            $offMachineAt = $closedAt;
            $repairMinutes = null;
        } else {
            if ($repairMinutes === 0) {
                $reportDate = $diesCorrective->report_date instanceof \Carbon\Carbon
                    ? $diesCorrective->report_date
                    : \Carbon\Carbon::parse($diesCorrective->report_date);
                $repairMinutes = (int) $reportDate->diffInMinutes($closedAt);
            }
        }

        $diesCorrective->update([
            'status'                   => 'closed',
            'action'                   => $request->action,
            'closed_by'                => Auth::id(),
            'closed_at'                => $closedAt,
            'off_machine_at'           => $offMachineAt,
            'machine_duration_minutes' => $machineDurationMinutes,
            'repair_duration_minutes'  => $repairMinutes,
        ]);

        return back()->with('success', 'Laporan corrective berhasil ditutup.');
    }

    public function destroy(DiesCorrective $diesCorrective)
    {
        DB::transaction(function () use ($diesCorrective) {
            foreach ($diesCorrective->spareparts as $history) {
                $history->sparepart?->increment('stok', $history->quantity);
                $history->delete();
            }
            $diesCorrective->repairSessions()->delete();
            if ($diesCorrective->photos) {
                foreach ($diesCorrective->photos as $photo) {
                    Storage::disk('public')->delete($photo['path']);
                }
            }
            $diesCorrective->delete();
        });

        return back()->with('success', 'Laporan corrective berhasil dihapus.');
    }

    public function deletePhoto(Request $request, DiesCorrective $diesCorrective)
    {
        $request->validate(['photo' => 'required|string']);

        $photos = collect($diesCorrective->photos ?? [])
            ->reject(fn($p) => $p['path'] === $request->photo)
            ->values()->all();

        Storage::disk('public')->delete($request->photo);
        $diesCorrective->update(['photos' => $photos ?: null]);

        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
