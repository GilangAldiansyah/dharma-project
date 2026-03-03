<?php

namespace App\Http\Controllers;

use App\Models\CmReport;
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
        $isPic    = !$isLeader && $user->hasRole('pic_jig');

        $query = CmReport::with('jig:id,name,type,line', 'pic:id,name', 'spareparts.sparepart:id,name,satuan')
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest('report_date');

        $reports = $query->get();

        $baseQ = CmReport::when($isPic, fn($q) => $q->where('pic_id', $user->id));
        $summary = [
            'open'        => (clone $baseQ)->where('status', 'open')->count(),
            'in_progress' => (clone $baseQ)->where('status', 'in_progress')->count(),
            'closed'      => (clone $baseQ)->where('status', 'closed')->count(),
        ];

        $jigs = \App\Models\Jig::select('id', 'name', 'type', 'line')
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->orderBy('name')->get();

        return Inertia::render('Cm/Index', [
            'reports'    => $reports,
            'jigs'       => $jigs,
            'spareparts' => Sparepart::select('id', 'name', 'satuan', 'stok')->orderBy('name')->get(),
            'summary'    => $summary,
            'filters'    => ['status' => $request->status ?? ''],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jig_id'      => 'required|exists:jigs,id',
            'description' => 'required|string',
            'photo'       => 'nullable|image|max:5120',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'required|exists:spareparts,id',
            'spareparts.*.qty'          => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($request) {
            $photoPath = $request->file('photo')?->store('cm-reports', 'public');

            $cm = CmReport::create([
                'jig_id'      => $request->jig_id,
                'pic_id'      => Auth::id(),
                'report_date' => now()->toDateString(),
                'description' => $request->description,
                'photo'       => $photoPath,
                'status'      => 'open',
            ]);

            if ($request->spareparts) {
                foreach ($request->spareparts as $sp) {
                    ReportSparepart::create([
                        'report_type'  => 'cm',
                        'report_id'    => $cm->id,
                        'sparepart_id' => $sp['sparepart_id'],
                        'qty'          => $sp['qty'],
                    ]);
                    Sparepart::find($sp['sparepart_id'])?->kurangiStok($sp['qty']);
                }
            }
        });

        return back()->with('success', 'Laporan CM berhasil dibuat.');
    }

    public function update(Request $request, CmReport $cmReport)
    {
        $cmReport->update(['status' => 'in_progress']);
        return back()->with('success', 'Status CM diperbarui.');
    }

    public function close(Request $request, CmReport $cmReport)
    {
        $request->validate(['action' => 'required|string']);
        $cmReport->update([
            'status'    => 'closed',
            'action'    => $request->action,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);
        return back()->with('success', 'CM berhasil ditutup.');
    }
}
