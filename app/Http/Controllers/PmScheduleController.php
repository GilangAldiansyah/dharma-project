<?php

namespace App\Http\Controllers;

use App\Models\Jig;
use App\Models\PmReport;
use App\Models\PmSchedule;
use App\Models\User;
use App\Services\PmScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PmScheduleController extends Controller
{
    public function __construct(private PmScheduleService $service) {}

    public function index(Request $request)
    {
        $query = PmSchedule::with('jig.pic:id,name');

        if ($request->filled('pic_id')) {
            $query->whereHas('jig', fn($q) => $q->where('pic_id', $request->pic_id));
        }
        if ($request->filled('tahun')) {
            $query->where('tahun', $request->tahun);
        }
        if ($request->filled('interval')) {
            $query->where('interval', $request->interval);
        }

        $schedules = $query->orderBy('tahun', 'desc')->get();
        $jigs      = Jig::with('pic:id,name')->where('kategori', '!=', 'discontinue')->orderBy('name')->get(['id','name','type','line','kategori','pic_id']);
        $pics      = User::whereHas('roles', fn($q) => $q->where('name','pic_jig'))->select('id','name')->orderBy('name')->get();

        $previewTahun   = $request->filled('preview_tahun') ? (int) $request->preview_tahun : now()->year;
        $existingJigIds = PmSchedule::where('tahun', $previewTahun)->pluck('jig_id')->toArray();

        $jigsBelumAda = Jig::with('pic:id,name')
            ->where('kategori', '!=', 'discontinue')
            ->whereNotIn('id', $existingJigIds)
            ->orderBy('name')
            ->get(['id','name','type','line','kategori','pic_id']);

        return Inertia::render('Pm/Schedule', [
            'schedules'    => $schedules,
            'jigs'         => $jigs,
            'pics'         => $pics,
            'jigsBelumAda' => $jigsBelumAda,
            'previewTahun' => $previewTahun,
            'filters'      => $request->only('pic_id','tahun','interval'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jig_id'   => 'required|exists:jigs,id',
            'interval' => 'required|in:1_bulan,3_bulan',
            'tahun'    => 'required|integer|min:2020|max:2099',
        ]);

        $exists = PmSchedule::where('jig_id', $request->jig_id)->where('tahun', $request->tahun)->exists();
        if ($exists) {
            return back()->withErrors(['jig_id' => 'Schedule untuk JIG ini di tahun tersebut sudah ada.']);
        }

        $schedule = PmSchedule::create($request->only('jig_id','interval','tahun'));
        $schedule->load('jig');
        $this->service->generateReports($schedule);

        return back()->with('success', 'Schedule berhasil dibuat dan laporan PM telah di-generate.');
    }

    /**
     * Update interval atau tahun. Jika interval berubah → hapus laporan pending lama, generate ulang.
     */
    public function update(Request $request, PmSchedule $pmSchedule)
    {
        $request->validate([
            'interval' => 'required|in:1_bulan,3_bulan',
            'tahun'    => 'required|integer|min:2020|max:2099',
        ]);

        $intervalChanged = $pmSchedule->interval !== $request->interval;
        $tahunChanged    = (int) $pmSchedule->tahun !== (int) $request->tahun;

        DB::transaction(function () use ($request, $pmSchedule, $intervalChanged, $tahunChanged) {
            // Jika interval atau tahun berubah → hapus laporan pending lalu generate ulang
            if ($intervalChanged || $tahunChanged) {
                // Hanya hapus yg masih pending (yang sudah done/late jangan dihapus)
                PmReport::where('pm_schedule_id', $pmSchedule->id)
                    ->where('status', 'pending')
                    ->delete();

                $pmSchedule->update([
                    'interval' => $request->interval,
                    'tahun'    => $request->tahun,
                ]);

                $pmSchedule->load('jig');
                $this->service->generateReports($pmSchedule);
            }
        });

        return back()->with('success', 'Schedule berhasil diupdate' . ($intervalChanged || $tahunChanged ? ' dan laporan PM di-generate ulang.' : '.'));
    }

    public function generateBulk(Request $request)
    {
        $request->validate([
            'tahun'       => 'required|integer|min:2020|max:2099',
            'skip_exists' => 'boolean',
        ]);

        $tahun     = (int) $request->tahun;
        $skipExist = $request->boolean('skip_exists', true);
        $intervalMap = ['regular' => '1_bulan', 'slow_moving' => '3_bulan', 'discontinue' => null];

        $jigs = Jig::where('kategori', '!=', 'discontinue')->get();
        if ($jigs->isEmpty()) {
            return back()->withErrors(['tahun' => 'Tidak ada JIG regular/slow moving di master.']);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($jigs, $tahun, $skipExist, $intervalMap, &$created, &$skipped) {
            foreach ($jigs as $jig) {
                $interval = $intervalMap[$jig->kategori] ?? null;
                if (!$interval) continue;

                $existing = PmSchedule::where('jig_id', $jig->id)->where('tahun', $tahun)->first();
                if ($existing) {
                    if ($skipExist) { $skipped++; continue; }
                    PmReport::where('pm_schedule_id', $existing->id)->where('status','pending')->delete();
                    $existing->delete();
                }

                $schedule = PmSchedule::create(['jig_id' => $jig->id, 'interval' => $interval, 'tahun' => $tahun]);
                $schedule->setRelation('jig', $jig);
                $this->service->generateReports($schedule);
                $created++;
            }
        });

        $msg = "Berhasil generate {$created} schedule PM untuk tahun {$tahun}.";
        if ($skipped > 0) $msg .= " {$skipped} JIG dilewati karena sudah punya schedule.";

        return back()->with('success', $msg);
    }

    public function destroy(PmSchedule $pmSchedule)
    {
        $pmSchedule->delete();
        return back()->with('success', 'Schedule berhasil dihapus.');
    }
}
