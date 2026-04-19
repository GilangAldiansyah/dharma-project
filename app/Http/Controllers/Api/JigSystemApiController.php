<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmReport;
use App\Models\Jig;
use App\Models\PmReport;
use App\Models\ReportSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JigSystemApiController extends Controller
{
    public function getJigs(Request $request)
    {
        $query = Jig::with('pic:id,name')
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->line, fn($q) => $q->where('line', $request->line))
            ->orderBy('name');

        return response()->json(['data' => $query->get()]);
    }

    public function createJig(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'type'     => 'required|string|max:100',
            'line'     => 'required|string|max:100',
            'kategori' => 'required|string|max:100',
            'description' => 'nullable|string',
            'pic_id'   => 'nullable|exists:users,id',
        ]);

        $jig = Jig::create([
            'name'        => $request->name,
            'type'        => $request->type,
            'line'        => $request->line,
            'kategori'    => $request->kategori,
            'description' => $request->description,
            'pic_id'      => $request->pic_id ?? Auth::id(),
        ]);

        return response()->json($jig->load('pic:id,name'), 201);
    }

    public function updateJig(Request $request, $id)
    {
        $jig = Jig::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|max:100',
            'line'        => 'required|string|max:100',
            'kategori'    => 'required|string|max:100',
            'description' => 'nullable|string',
            'pic_id'      => 'nullable|exists:users,id',
        ]);

        $jig->update([
            'name'        => $request->name,
            'type'        => $request->type,
            'line'        => $request->line,
            'kategori'    => $request->kategori,
            'description' => $request->description,
            'pic_id'      => $request->pic_id ?? $jig->pic_id,
        ]);

        return response()->json($jig->load('pic:id,name'));
    }

    public function deleteJig($id)
    {
        $jig = Jig::findOrFail($id);
        $jig->delete();

        return response()->json(['message' => 'Jig deleted successfully']);
    }

    public function getPmReports(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');
        $isPic    = !$isLeader && $user->hasRole('pic_jig');

        $bulan  = $request->filled('bulan')  ? $request->bulan        : now()->month;
        $tahun  = $request->filled('tahun')  ? $request->tahun        : now()->year;
        $minggu = $request->filled('minggu') ? (int) $request->minggu : null;

        $query = PmReport::with([
            'pmSchedule.jig:id,name,type,line',
            'pic:id,name',
            'nokClosedBy:id,name',
            'spareparts.sparepart:id,name,satuan',
        ])
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan))
            ->whereYear('planned_week_start', $tahun)
            ->when($minggu, fn($q) => $q->whereRaw('CEIL(DAY(planned_week_start)/7) = ?', [$minggu]))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('planned_week_start')
            ->orderBy('created_at');

        return response()->json(['data' => $query->get()]);
    }

    public function getPmReportSummary(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');
        $isPic    = !$isLeader && $user->hasRole('pic_jig');

        $bulan  = $request->filled('bulan')  ? $request->bulan        : now()->month;
        $tahun  = $request->filled('tahun')  ? $request->tahun        : now()->year;
        $minggu = $request->filled('minggu') ? (int) $request->minggu : null;

        $base = PmReport::when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->whereYear('planned_week_start', $tahun)
            ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan))
            ->when($minggu, fn($q) => $q->whereRaw('CEIL(DAY(planned_week_start)/7) = ?', [$minggu]));

        return response()->json([
            'total'   => (clone $base)->count(),
            'done'    => (clone $base)->where('status', 'done')->count(),
            'late'    => (clone $base)->where('status', 'late')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
        ]);
    }

    public function submitPmReport(Request $request, $id)
    {
        $pmReport = PmReport::findOrFail($id);

        $request->validate([
            'photo'                     => 'required|image|max:5120',
            'photo_sparepart'           => 'nullable|image|max:5120',
            'condition'                 => 'required|in:ok,nok',
            'notes'                     => 'nullable|string',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'required|exists:spareparts,id',
            'spareparts.*.qty'          => 'required|numeric|min:0.01',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $pmReport) {
            $photoPath      = $request->file('photo')->store('pm-reports', 'public');
            $photoSparepart = $request->file('photo_sparepart')?->store('pm-reports', 'public');
            $actualDate     = Carbon::today();
            $status         = $actualDate->isAfter($pmReport->planned_week_end) ? 'late' : 'done';

            $pmReport->update([
                'actual_date'     => $actualDate,
                'photo'           => $photoPath,
                'photo_sparepart' => $photoSparepart,
                'condition'       => $request->condition,
                'notes'           => $request->notes,
                'status'          => $status,
            ]);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    ReportSparepart::create([
                        'report_type'  => 'pm',
                        'report_id'    => $pmReport->id,
                        'sparepart_id' => $sp['sparepart_id'],
                        'qty'          => $sp['qty'],
                        'notes'        => $sp['notes'] ?? null,
                    ]);
                    Sparepart::find($sp['sparepart_id'])
                        ?->kurangiStok($sp['qty'], 'pm', $pmReport->id, $sp['notes'] ?? null);
                }
            }
        });

        return response()->json([
            'message' => 'Laporan PM berhasil dikirim.',
            'data'    => $pmReport->fresh([
                'pmSchedule.jig:id,name,type,line',
                'pic:id,name',
                'spareparts.sparepart:id,name,satuan',
            ]),
        ]);
    }

    public function closeNokPmReport(Request $request, $id)
    {
        $pmReport = PmReport::findOrFail($id);

        abort_if($pmReport->condition !== 'nok', 403, 'Hanya laporan NOK yang bisa di-close.');

        $request->validate([
            'nok_notes' => 'nullable|string',
        ]);

        $pmReport->update([
            'condition'     => 'ok',
            'nok_closed_by' => Auth::id(),
            'nok_closed_at' => now(),
            'notes'         => $request->nok_notes
                ? ($pmReport->notes ? $pmReport->notes . "\n[Close NOK] " . $request->nok_notes : '[Close NOK] ' . $request->nok_notes)
                : $pmReport->notes,
        ]);

        return response()->json([
            'message' => 'Status JIG berhasil diubah menjadi OK.',
            'data'    => $pmReport->fresh('nokClosedBy:id,name'),
        ]);
    }

    public function getCmReports(Request $request)
    {
        $year = $request->year ?? now()->year;

        $query = CmReport::with([
            'jig:id,name,type,line',
            'pic:id,name',
            'closedBy:id,name',
            'spareparts.sparepart:id,name,satuan',
        ])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->jig_id, fn($q) => $q->where('jig_id', $request->jig_id))
            ->when($request->month, fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
            ->when(!$request->month, fn($q) => $q->whereYear('report_date', $year))
            ->latest('report_date');

        $data = $query->get()->map(fn($r) => array_merge($r->toArray(), [
            'repair_duration' => $r->repair_duration,
        ]));

        return response()->json(['data' => $data]);
    }

    public function getCmReportSummary(Request $request)
    {
        $year = $request->year ?? now()->year;

        $base = CmReport::query()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->month, fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
            ->when(!$request->month, fn($q) => $q->whereYear('report_date', $year));

        return response()->json([
            'open'                 => (clone $base)->where('status', 'open')->count(),
            'in_progress'          => (clone $base)->where('status', 'in_progress')->count(),
            'closed'               => (clone $base)->where('status', 'closed')->count(),
            'total_repair_minutes' => (int) (clone $base)
                ->whereNotNull('closed_at')
                ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, report_date, closed_at)) as total')
                ->value('total'),
        ]);
    }

    public function createCmReport(Request $request)
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

        $cm = DB::transaction(function () use ($request) {
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

            return $cm;
        });

        return response()->json([
            'message' => 'Laporan CM berhasil dibuat.',
            'data'    => $cm->load([
                'jig:id,name,type,line',
                'pic:id,name',
                'spareparts.sparepart:id,name,satuan',
            ]),
        ], 201);
    }

    public function updateCmReport(Request $request, $id)
    {
        $cmReport = CmReport::findOrFail($id);

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

        return response()->json([
            'message' => 'Laporan CM berhasil diperbarui.',
            'data'    => $cmReport->fresh([
                'jig:id,name,type,line',
                'pic:id,name',
                'spareparts.sparepart:id,name,satuan',
            ]),
        ]);
    }

    public function closeCmReport(Request $request, $id)
    {
        $cmReport = CmReport::findOrFail($id);

        $request->validate(['action' => 'nullable|string']);

        $cmReport->update([
            'status'    => 'closed',
            'action'    => $request->action,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'CM berhasil ditutup.',
            'data'    => $cmReport->fresh('closedBy:id,name'),
        ]);
    }

    public function deleteCmReport($id)
    {
        $cmReport = CmReport::findOrFail($id);
        $cmReport->delete();

        return response()->json(['message' => 'Laporan CM berhasil dihapus.']);
    }

    public function getSpareparts()
    {
        $spareparts = Sparepart::select('id', 'name', 'satuan', 'stok')->orderBy('name')->get();

        return response()->json(['data' => $spareparts]);
    }

    public function getDashboard(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year  = $request->year  ?? now()->year;

        $pmBase = PmReport::whereYear('planned_week_start', $year)
            ->whereMonth('planned_week_start', $month);

        $cmBase = CmReport::whereYear('report_date', $year)
            ->whereMonth('report_date', $month);

        $pmSummary = [
            'total'   => (clone $pmBase)->count(),
            'done'    => (clone $pmBase)->where('status', 'done')->count(),
            'late'    => (clone $pmBase)->where('status', 'late')->count(),
            'pending' => (clone $pmBase)->where('status', 'pending')->count(),
        ];

        $cmSummary = [
            'total'                => (clone $cmBase)->count(),
            'open'                 => (clone $cmBase)->where('status', 'open')->count(),
            'in_progress'          => (clone $cmBase)->where('status', 'in_progress')->count(),
            'closed'               => (clone $cmBase)->where('status', 'closed')->count(),
            'total_repair_minutes' => (int) (clone $cmBase)
                ->whereNotNull('closed_at')
                ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, report_date, closed_at)) as total')
                ->value('total'),
        ];

        $jigCount = Jig::count();

        $recentCm = CmReport::with('jig:id,name,type,line')
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->latest('report_date')
            ->limit(5)
            ->get()
            ->map(fn($r) => array_merge($r->toArray(), ['repair_duration' => $r->repair_duration]));

        return response()->json([
            'pm_summary'  => $pmSummary,
            'cm_summary'  => $cmSummary,
            'jig_count'   => $jigCount,
            'recent_cm'   => $recentCm,
            'month'       => $month,
            'year'        => $year,
        ]);
    }
}
