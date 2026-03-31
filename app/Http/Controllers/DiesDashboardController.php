<?php
namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesPreventive;
use App\Models\DiesCorrective;
use App\Models\DiesProcess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DiesDashboardController extends Controller
{
    public function index()
    {
        $totalDies   = Dies::count();
        $totalActive = Dies::where('status', 'active')->count();

        $overdue = Dies::where('status', 'active')
            ->whereRaw('std_stroke > 0')
            ->whereRaw('current_stroke >= std_stroke')
            ->count();

        $dueSoon = Dies::where('status', 'active')
            ->whereRaw('std_stroke > 0')
            ->whereRaw('current_stroke < std_stroke')
            ->whereRaw('(current_stroke / std_stroke) >= 0.85')
            ->count();

        $correctiveOpen = DiesCorrective::whereIn('status', ['open', 'in_progress'])->count();

        $dueList = Dies::where('status', 'active')
            ->whereRaw('std_stroke > 0')
            ->whereRaw('(current_stroke / std_stroke) >= 0.75')
            ->orderByRaw('(current_stroke / std_stroke) DESC')
            ->with('processes')
            ->limit(50)
            ->get([
                'id_sap', 'no_part', 'nama_dies', 'line',
                'std_stroke', 'current_stroke', 'forecast_per_day',
                'last_mtc_date', 'freq_maintenance', 'freq_maintenance_day',
            ])
            ->map(function ($d) {
                $pct       = $d->std_stroke > 0 ? round(($d->current_stroke / $d->std_stroke) * 100, 2) : 0;
                $remaining = max(0, $d->std_stroke - $d->current_stroke);
                $fpd       = $d->forecast_per_day ?? 0;
                $daysLeft  = $fpd > 0 ? ceil($remaining / $fpd) : null;
                $estMtcDate = $daysLeft !== null ? now()->addDays($daysLeft)->toDateString() : null;
                $statusMtc  = $this->getStatusMtc($pct, $d->last_mtc_date, $d->freq_maintenance_day);

                $forecasts = [];
                for ($h = 1; $h <= 5; $h++) {
                    $fStroke = $d->current_stroke + ($fpd * $h);
                    $fPct    = $d->std_stroke > 0 ? round($fStroke / $d->std_stroke * 100, 2) : 0;
                    $forecasts[] = [
                        'day'        => $h,
                        'stroke'     => $fStroke,
                        'percentage' => $fPct,
                        'status'     => $this->getDiesStatus($fPct),
                    ];
                }

                $scheduled = DiesPreventive::where('dies_id', $d->id_sap)
                    ->where('status', 'scheduled')
                    ->first(['id', 'scheduled_date', 'process_id', 'pic_name']);

                return [
                    'id_sap'               => $d->id_sap,
                    'no_part'              => $d->no_part,
                    'nama_dies'            => $d->nama_dies,
                    'line'                 => $d->line,
                    'std_stroke'           => $d->std_stroke,
                    'current_stroke'       => $d->current_stroke,
                    'forecast_per_day'     => $fpd,
                    'percentage'           => $pct,
                    'days_left'            => $daysLeft,
                    'est_mtc_date'         => $estMtcDate,
                    'last_mtc_date'        => $d->last_mtc_date,
                    'freq_maintenance'     => $d->freq_maintenance,
                    'status_mtc'           => $statusMtc,
                    'dies_status'          => $this->getDiesStatus($pct),
                    'is_overdue'           => $pct >= 100,
                    'forecasts'            => $forecasts,
                    'has_scheduled'        => $scheduled !== null,
                    'scheduled_id'         => $scheduled?->id,
                    'scheduled_date'       => $scheduled?->scheduled_date,
                    'scheduled_process_id' => $scheduled?->process_id,
                    'scheduled_pic_name'   => $scheduled?->pic_name,
                    'processes'            => $d->processes->map(fn($p) => [
                        'id'           => $p->id,
                        'process_name' => $p->process_name,
                        'tonase'       => $p->tonase,
                    ]),
                ];
            });

        $byLine = Dies::where('status', 'active')
            ->whereRaw('std_stroke > 0')
            ->select(
                'line',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN current_stroke >= std_stroke THEN 1 ELSE 0 END) as overdue'),
                DB::raw('SUM(CASE WHEN (current_stroke/std_stroke) >= 0.85 AND current_stroke < std_stroke THEN 1 ELSE 0 END) as due_soon'),
                DB::raw('SUM(CASE WHEN (current_stroke/std_stroke) >= 0.75 AND (current_stroke/std_stroke) < 0.85 THEN 1 ELSE 0 END) as due_warn')
            )
            ->groupBy('line')
            ->orderBy('line')
            ->get();

        $recentPm = DiesPreventive::with('dies:id_sap,no_part,nama_dies,line')
            ->whereNotIn('status', ['scheduled'])
            ->latest('report_date')
            ->limit(8)
            ->get(['id', 'report_no', 'dies_id', 'pic_name', 'report_date', 'status', 'stroke_at_maintenance']);

        $recentCm = DiesCorrective::with('dies:id_sap,no_part,nama_dies,line')
            ->latest('report_date')
            ->limit(8)
            ->get(['id', 'report_no', 'dies_id', 'pic_name', 'report_date', 'status', 'stroke_at_maintenance']);

        $strokeTrend = DiesPreventive::select(
                DB::raw("DATE_FORMAT(report_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as pm_count'),
                DB::raw('AVG(stroke_at_maintenance) as avg_stroke')
            )
            ->whereNotIn('status', ['scheduled'])
            ->where('report_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $picList = User::whereHas('roles', fn($q) => $q->where('name', 'pic_dies'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $cmPeriod = request('cm_period', '6');

        $cmRankingQuery = DiesCorrective::select(
                'dies_id',
                'process_id',
                DB::raw('COUNT(*) as cm_count'),
                DB::raw('MAX(report_date) as last_cm_date')
            )
            ->groupBy('dies_id', 'process_id')
            ->orderByDesc('cm_count')
            ->limit(10);

        if ($cmPeriod !== 'all') {
            $cmRankingQuery->where('report_date', '>=', now()->subMonths((int)$cmPeriod)->startOfMonth());
        }

        $cmRanking = $cmRankingQuery->get()->map(function ($row) {
            $dies    = Dies::find($row->dies_id, ['id_sap', 'no_part', 'nama_dies', 'line']);
            $process = DiesProcess::find($row->process_id, ['id', 'process_name', 'tonase']);
            return [
                'dies_id'      => $row->dies_id,
                'no_part'      => $dies?->no_part ?? $row->dies_id,
                'nama_dies'    => $dies?->nama_dies ?? '-',
                'line'         => $dies?->line ?? '-',
                'process_id'   => $row->process_id,
                'process_name' => $process?->process_name ?? '-',
                'tonase'       => $process?->tonase ?? null,
                'cm_count'     => (int)$row->cm_count,
                'last_cm_date' => $row->last_cm_date,
            ];
        });

        return Inertia::render('Dies/Dashboard', [
            'summary' => [
                'total_dies'      => $totalDies,
                'total_active'    => $totalActive,
                'overdue'         => $overdue,
                'due_soon'        => $dueSoon,
                'corrective_open' => $correctiveOpen,
            ],
            'dueList'     => $dueList,
            'byLine'      => $byLine,
            'recentPm'    => $recentPm,
            'recentCm'    => $recentCm,
            'strokeTrend' => $strokeTrend,
            'picList'     => $picList,
            'cmRanking'   => $cmRanking,
            'cmPeriod'    => $cmPeriod,
        ]);
    }

    public function schedulePm(Request $request)
    {
        $request->validate([
            'dies_id'        => 'required|exists:dies,id_sap',
            'process_id'     => 'required|exists:dies_processes,id',
            'scheduled_date' => 'required|date',
            'pic_id'         => 'required|exists:users,id',
        ]);

        $dies    = Dies::findOrFail($request->dies_id);
        $picUser = User::findOrFail($request->pic_id);

        $existing = DiesPreventive::where('dies_id', $request->dies_id)
            ->where('status', 'scheduled')
            ->first();

        if ($existing) {
            $existing->update([
                'process_id'     => $request->process_id,
                'scheduled_date' => $request->scheduled_date,
                'pic_name'       => $picUser->name,
            ]);
            return back()->with('success', 'Jadwal PM berhasil diperbarui.');
        }

        $yy  = now()->format('y');
        $mm  = now()->format('m');
        $dd  = now()->format('d');
        $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

        DiesPreventive::create([
            'report_no'             => "PM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
            'dies_id'               => $request->dies_id,
            'process_id'            => $request->process_id,
            'pic_name'              => $picUser->name,
            'report_date'           => $request->scheduled_date,
            'stroke_at_maintenance' => $dies->current_stroke,
            'status'                => 'scheduled',
            'scheduled_date'        => $request->scheduled_date,
            'created_by'            => Auth::id(),
        ]);

        return back()->with('success', 'PM berhasil dijadwalkan.');
    }

    private function getDiesStatus(float $pct): string
    {
        if ($pct >= 100) return 'Diharuskan';
        if ($pct >= 85)  return 'Disegerakan';
        if ($pct >= 75)  return 'Dijadwalkan';
        return 'Siap Pakai';
    }

    private function getStatusMtc(float $pct, $lastMtcDate, $freqDay): string
    {
        if ($pct >= 100) return 'DELAY MTC';
        if ($pct >= 85)  return 'Prepare MTC';
        return 'OK MTC';
    }
}
