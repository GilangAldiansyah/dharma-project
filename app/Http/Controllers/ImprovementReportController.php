<?php

namespace App\Http\Controllers;

use App\Models\ImprovementReport;
use App\Models\Jig;
use App\Models\ReportSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ImprovementReportController extends Controller
{
    public function index(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');

        $year = $request->year ?? now()->year;

        $reports = ImprovementReport::with([
            'jig:id,name,type,line',
            'pic:id,name',
            'closedBy:id,name',
            'spareparts.sparepart:id,name,satuan',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->jig_id, fn($q) => $q->where('jig_id', $request->jig_id))
        ->when($request->month, fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
        ->when(!$request->month, fn($q) => $q->whereYear('report_date', $year))
        ->latest('report_date')
        ->get()
        ->map(fn($r) => array_merge($r->toArray(), [
            'repair_duration' => $r->repair_duration,
        ]));

        $summary = [
            'open'        => ImprovementReport::where('status', 'open')->count(),
            'in_progress' => ImprovementReport::where('status', 'in_progress')->count(),
            'closed'      => ImprovementReport::where('status', 'closed')->count(),
        ];

        $jigs = Jig::select('id', 'name', 'type', 'line')
            ->orderBy('name')->get();

        return Inertia::render('Improvement/Index', [
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
            $photoPath      = $request->file('photo')?->store('improvement-reports', 'public');
            $photoPerbaikan = $request->file('photo_perbaikan')?->store('improvement-reports', 'public');

            $imp = ImprovementReport::create([
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
                        'report_type'  => 'improvement',
                        'report_id'    => $imp->id,
                        'sparepart_id' => $sp['sparepart_id'],
                        'qty'          => $sp['qty'],
                        'notes'        => $sp['notes'] ?? null,
                    ]);
                    Sparepart::find($sp['sparepart_id'])
                        ?->kurangiStok($sp['qty'], 'improvement', $imp->id, $sp['notes'] ?? null);
                }
            }
        });

        return back()->with('success', 'Laporan Improvement berhasil dibuat.');
    }

    public function update(Request $request, ImprovementReport $improvementReport)
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

        DB::transaction(function () use ($request, $improvementReport) {
            $data = ['status' => 'in_progress'];

            if ($request->filled('description')) $data['description'] = $request->description;
            if ($request->filled('penyebab'))    $data['penyebab']    = $request->penyebab;
            if ($request->filled('perbaikan'))   $data['perbaikan']   = $request->perbaikan;

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('improvement-reports', 'public');
            }
            if ($request->hasFile('photo_perbaikan')) {
                $data['photo_perbaikan'] = $request->file('photo_perbaikan')->store('improvement-reports', 'public');
            }

            $improvementReport->update($data);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    $existing = $improvementReport->spareparts()->where('sparepart_id', $sp['sparepart_id'])->first();
                    if (!$existing) {
                        ReportSparepart::create([
                            'report_type'  => 'improvement',
                            'report_id'    => $improvementReport->id,
                            'sparepart_id' => $sp['sparepart_id'],
                            'qty'          => $sp['qty'],
                            'notes'        => $sp['notes'] ?? null,
                        ]);
                        Sparepart::find($sp['sparepart_id'])
                            ?->kurangiStok($sp['qty'], 'improvement', $improvementReport->id, $sp['notes'] ?? null);
                    }
                }
            }
        });

        return back()->with('success', 'Laporan Improvement berhasil diperbarui.');
    }

    public function close(Request $request, ImprovementReport $improvementReport)
    {
        $request->validate(['action' => 'nullable|string']);

        $improvementReport->update([
            'status'    => 'closed',
            'action'    => $request->action,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Improvement berhasil ditutup.');
    }
}
