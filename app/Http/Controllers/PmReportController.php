<?php

namespace App\Http\Controllers;

use App\Models\PmReport;
use App\Models\ReportSparepart;
use App\Models\Sparepart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PmReportController extends Controller
{
    public function index(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');
        $isPic    = !$isLeader && $user->hasRole('pic_jig');

        $bulan = $request->filled('bulan') ? $request->bulan : now()->month;
        $tahun = $request->filled('tahun') ? $request->tahun : now()->year;

        $query = PmReport::with([
            'pmSchedule.jig:id,name,type,line',
            'pic:id,name',
            'spareparts.sparepart:id,name,satuan',
        ])->when($isPic, fn($q) => $q->where('pic_id', $user->id))
          ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan))
          ->whereYear('planned_week_start', $tahun)
          ->when($request->status, fn($q) => $q->where('status', $request->status))
          ->orderBy('planned_week_start');

        $reports = $query->get();

        $baseQuery = PmReport::when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->whereYear('planned_week_start', $tahun)
            ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan));

        $summary = [
            'total'   => (clone $baseQuery)->count(),
            'done'    => (clone $baseQuery)->where('status', 'done')->count(),
            'late'    => (clone $baseQuery)->where('status', 'late')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
        ];

        return Inertia::render('Pm/Report', [
            'reports'    => $reports,
            'spareparts' => Sparepart::select('id', 'name', 'satuan', 'stok')->orderBy('name')->get(),
            'summary'    => $summary,
            'filters'    => [
                'bulan'  => $bulan,
                'tahun'  => $tahun,
                'status' => $request->status ?? '',
            ],
        ]);
    }

    public function submit(Request $request, PmReport $pmReport)
    {
        $request->validate([
            'photo'                     => 'required|image|max:5120',
            'condition'                 => 'required|in:ok,nok',
            'notes'                     => 'nullable|string',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'required|exists:spareparts,id',
            'spareparts.*.qty'          => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($request, $pmReport) {
            $photoPath  = $request->file('photo')->store('pm-reports', 'public');
            $actualDate = Carbon::today();
            $status     = $actualDate->isAfter($pmReport->planned_week_end) ? 'late' : 'done';

            $pmReport->update([
                'actual_date' => $actualDate,
                'photo'       => $photoPath,
                'condition'   => $request->condition,
                'notes'       => $request->notes,
                'status'      => $status,
            ]);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    ReportSparepart::create([
                        'report_type'  => 'pm',
                        'report_id'    => $pmReport->id,
                        'sparepart_id' => $sp['sparepart_id'],
                        'qty'          => $sp['qty'],
                    ]);
                    Sparepart::find($sp['sparepart_id'])?->kurangiStok($sp['qty']);
                }
            }
        });

        return back()->with('success', 'Laporan PM berhasil dikirim.');
    }
}
