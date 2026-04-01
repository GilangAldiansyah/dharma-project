<?php

namespace App\Http\Controllers;

use App\Models\CmReport;
use App\Models\Jig;
use App\Models\ReportSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CmReportController extends Controller
{
    public function index(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');

        $year = $request->year ?? now()->year;

        $baseQuery = CmReport::query()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->jig_id, fn($q) => $q->where('jig_id', $request->jig_id))
            ->when($request->month,  fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
            ->when(!$request->month, fn($q) => $q->whereYear('report_date', $year));

        $reports = (clone $baseQuery)
            ->with([
                'jig:id,name,type,line',
                'pic:id,name',
                'closedBy:id,name',
                'spareparts.sparepart:id,name,satuan',
            ])
            ->latest('report_date')
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), [
                'repair_duration' => $r->repair_duration,
            ]));

        $summary = [
            'open'                 => (clone $baseQuery)->where('status', 'open')->count(),
            'in_progress'          => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'closed'               => (clone $baseQuery)->where('status', 'closed')->count(),
            'total_repair_minutes' => (int) (clone $baseQuery)
                                        ->whereNotNull('closed_at')
                                        ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, report_date, closed_at)) as total')
                                        ->value('total'),
        ];

        $jigs = Jig::select('id', 'name', 'type', 'line')
            ->orderBy('name')->get();

        return Inertia::render('Cm/Index', [
            'reports'    => $reports,
            'jigs'       => $jigs,
            'spareparts' => Sparepart::select('id', 'name', 'satuan', 'stok')->orderBy('name')->get(),
            'summary'    => $summary,
            'isLeader'   => $isLeader,
            'authId'     => Auth::id(),
            'filters'    => [
                'status' => $request->status ?? '',
                'jig_id' => $request->jig_id ?? '',
                'month'  => $request->month  ?? '',
                'year'   => (string) $year,
            ],
        ]);
    }

    public function quickStoreJig(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $jig = Jig::create([
            'name'     => $request->name,
            'type'     => '-',
            'line'     => '-',
            'kategori' => 'regular',
            'pic_id'   => Auth::id(),
        ]);

        return response()->json([
            'id'   => $jig->id,
            'name' => $jig->name,
            'type' => $jig->type,
            'line' => $jig->line,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jig_id'                    => 'required|exists:jigs,id',
            'description'               => 'nullable|string',
            'penyebab'                  => 'nullable|string',
            'perbaikan'                 => 'nullable|string',
            'photo'                     => 'nullable|image|max:5120',
            'photo_perbaikan'           => 'nullable|image|max:5120',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'required|exists:spareparts,id',
            'spareparts.*.qty'          => 'required|numeric|min:0.01',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $photoPath      = $request->file('photo')?->store('cm-reports', 'public');
            $photoPerbaikan = $request->file('photo_perbaikan')?->store('cm-reports', 'public');

            $cm = CmReport::create([
                'jig_id'          => $request->jig_id,
                'pic_id'          => Auth::id(),
                'report_date'     => now(),
                'description'     => $request->description,
                'penyebab'        => $request->penyebab,
                'perbaikan'       => $request->perbaikan,
                'photo'           => $photoPath,
                'photo_perbaikan' => $photoPerbaikan,
                'status'          => 'open',
            ]);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    ReportSparepart::create([
                        'report_type'  => 'cm',
                        'report_id'    => $cm->id,
                        'sparepart_id' => $sp['sparepart_id'],
                        'qty'          => $sp['qty'],
                        'notes'        => $sp['notes'] ?? null,
                    ]);
                    Sparepart::find($sp['sparepart_id'])
                        ?->kurangiStok($sp['qty'], 'cm', $cm->id, $sp['notes'] ?? null);
                }
            }
        });

        return back()->with('success', 'Laporan CM berhasil dibuat.');
    }

    public function update(Request $request, CmReport $cmReport)
    {
        $request->validate([
            'description'               => 'nullable|string',
            'penyebab'                  => 'nullable|string',
            'perbaikan'                 => 'nullable|string',
            'photo'                     => 'nullable|image|max:5120',
            'photo_perbaikan'           => 'nullable|image|max:5120',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'required|exists:spareparts,id',
            'spareparts.*.qty'          => 'required|numeric|min:0.01',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $cmReport) {
            $data = ['status' => 'in_progress'];

            if ($request->filled('description')) $data['description'] = $request->description;
            if ($request->filled('penyebab'))    $data['penyebab']    = $request->penyebab;
            if ($request->filled('perbaikan'))   $data['perbaikan']   = $request->perbaikan;

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('cm-reports', 'public');
            }
            if ($request->hasFile('photo_perbaikan')) {
                $data['photo_perbaikan'] = $request->file('photo_perbaikan')->store('cm-reports', 'public');
            }

            $cmReport->update($data);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    $existing = $cmReport->spareparts()->where('sparepart_id', $sp['sparepart_id'])->first();
                    if (!$existing) {
                        ReportSparepart::create([
                            'report_type'  => 'cm',
                            'report_id'    => $cmReport->id,
                            'sparepart_id' => $sp['sparepart_id'],
                            'qty'          => $sp['qty'],
                            'notes'        => $sp['notes'] ?? null,
                        ]);
                        Sparepart::find($sp['sparepart_id'])
                            ?->kurangiStok($sp['qty'], 'cm', $cmReport->id, $sp['notes'] ?? null);
                    }
                }
            }
        });

        return back()->with('success', 'Laporan CM berhasil diperbarui.');
    }

    public function close(Request $request, CmReport $cmReport)
    {
        $request->validate(['action' => 'nullable|string']);

        $cmReport->update([
            'status'    => 'closed',
            'action'    => $request->action,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return back()->with('success', 'CM berhasil ditutup.');
    }
}
