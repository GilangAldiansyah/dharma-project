<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesCorrective;
use App\Models\DiesHistorySparepart;
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
        $year = $request->year ?? now()->year;

        $query = DiesCorrective::with([
            'dies',
            'process',
            'spareparts.sparepart',
            'createdBy',
            'closedBy',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->dies_id, fn($q) => $q->where('dies_id', $request->dies_id))
        ->when($request->month, fn($q) => $q->whereRaw("DATE_FORMAT(report_date, '%Y-%m') = ?", [$year . '-' . $request->month]))
        ->when(!$request->month, fn($q) => $q->whereYear('report_date', $year))
        ->when($request->search, fn($q) => $q->where(function ($sq) use ($request) {
            $sq->where('report_no', 'like', '%' . $request->search . '%')
               ->orWhere('pic_name', 'like', '%' . $request->search . '%')
               ->orWhereHas('dies', fn($d) => $d->where('no_part', 'like', '%' . $request->search . '%'));
        }))
        ->latest('report_date');

        $correctives = $query->paginate(20)->withQueryString();

        $summary = [
            'open'        => DiesCorrective::where('status', 'open')->count(),
            'in_progress' => DiesCorrective::where('status', 'in_progress')->count(),
            'closed'      => DiesCorrective::where('status', 'closed')->count(),
        ];

        $diesList = Dies::with('processes')
            ->orderBy('no_part')
            ->get(['id_sap', 'no_part', 'nama_dies', 'line', 'current_stroke']);

        $spareparts = DiesSparepart::orderBy('sparepart_name')
            ->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);

        return Inertia::render('Dies/Corrective/Index', [
            'correctives' => $correctives,
            'diesList'    => $diesList,
            'spareparts'  => $spareparts,
            'summary'     => $summary,
            'filters'     => $request->only('search', 'status', 'dies_id', 'month', 'year') + ['year' => (string) $year],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dies_id'                  => 'required|exists:dies,id_sap',
            'process_id'               => 'required|exists:dies_processes,id',
            'stroke_at_maintenance'    => 'nullable|integer|min:0',
            'problem_description'      => 'nullable|string',
            'cause'                    => 'nullable|string',
            'repair_action'            => 'nullable|string',
            'photos_before'            => 'nullable|array',
            'photos_before.*'          => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'photos_after'             => 'nullable|array',
            'photos_after.*'           => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'spareparts'               => 'nullable|array',
            'spareparts.*.sparepart_id'=> 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'    => 'nullable|integer|min:1',
            'spareparts.*.notes'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $photos = [];

            if ($request->hasFile('photos_before')) {
                foreach ($request->file('photos_before') as $photo) {
                    $photos[] = [
                        'path' => $photo->store('dies/corrective', 'public'),
                        'type' => 'before',
                    ];
                }
            }

            if ($request->hasFile('photos_after')) {
                foreach ($request->file('photos_after') as $photo) {
                    $photos[] = [
                        'path' => $photo->store('dies/corrective', 'public'),
                        'type' => 'after',
                    ];
                }
            }

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $dies = Dies::find($request->dies_id);

            $corrective = DiesCorrective::create([
                'report_no'               => "CM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'                 => $request->dies_id,
                'process_id'              => $request->process_id,
                'pic_name'                => Auth::user()->name,
                'report_date'             => now(),
                'stroke_at_maintenance'   => $request->stroke_at_maintenance ?? $dies?->current_stroke ?? 0,
                'repair_duration_minutes' => null,
                'problem_description'     => $request->problem_description,
                'cause'                   => $request->cause,
                'repair_action'           => $request->repair_action,
                'photos'                  => $photos ?: null,
                'status'                  => 'open',
                'created_by'              => Auth::id(),
            ]);

            if ($request->has('spareparts') && is_array($request->spareparts)) {
                foreach ($request->spareparts as $sp) {
                    if (empty($sp['sparepart_id'])) continue;
                    $sparepart = DiesSparepart::findOrFail($sp['sparepart_id']);
                    $qty = (int) $sp['quantity'];
                    if ($sparepart->stok < $qty) {
                        throw new \Exception("Stok {$sparepart->sparepart_name} tidak mencukupi.");
                    }
                    DiesHistorySparepart::create([
                        'tipe'           => 'corrective',
                        'maintenance_id' => $corrective->id,
                        'sparepart_id'   => $sp['sparepart_id'],
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
            'problem_description'      => 'nullable|string',
            'cause'                    => 'nullable|string',
            'repair_action'            => 'nullable|string',
            'photos_before'            => 'nullable|array',
            'photos_before.*'          => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'photos_after'             => 'nullable|array',
            'photos_after.*'           => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'spareparts'               => 'nullable|array',
            'spareparts.*.sparepart_id'=> 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'    => 'nullable|integer|min:1',
            'spareparts.*.notes'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesCorrective) {
            $photos = $diesCorrective->photos ?? [];

            if ($request->hasFile('photos_before')) {
                foreach ($request->file('photos_before') as $photo) {
                    $photos[] = [
                        'path' => $photo->store('dies/corrective', 'public'),
                        'type' => 'before',
                    ];
                }
            }

            if ($request->hasFile('photos_after')) {
                foreach ($request->file('photos_after') as $photo) {
                    $photos[] = [
                        'path' => $photo->store('dies/corrective', 'public'),
                        'type' => 'after',
                    ];
                }
            }

            $data = [
                'status'              => 'in_progress',
                'problem_description' => $request->problem_description,
                'cause'               => $request->cause,
                'repair_action'       => $request->repair_action,
                'photos'              => $photos ?: null,
            ];

            $diesCorrective->update($data);

            if ($request->has('spareparts') && is_array($request->spareparts)) {
                foreach ($request->spareparts as $sp) {
                    if (empty($sp['sparepart_id'])) continue;
                    $sparepart = DiesSparepart::findOrFail($sp['sparepart_id']);
                    $qty = (int) $sp['quantity'];
                    if ($sparepart->stok < $qty) {
                        throw new \Exception("Stok {$sparepart->sparepart_name} tidak mencukupi.");
                    }
                    DiesHistorySparepart::create([
                        'tipe'           => 'corrective',
                        'maintenance_id' => $diesCorrective->id,
                        'sparepart_id'   => $sp['sparepart_id'],
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

    public function close(Request $request, DiesCorrective $diesCorrective)
    {
        $request->validate(['action' => 'nullable|string']);

        $closedAt = now();
        $reportDate = $diesCorrective->report_date instanceof \Carbon\Carbon
            ? $diesCorrective->report_date
            : \Carbon\Carbon::parse($diesCorrective->report_date);

        $durationMinutes = (int) $reportDate->diffInMinutes($closedAt);

        $diesCorrective->update([
            'status'                  => 'closed',
            'action'                  => $request->action,
            'closed_by'               => Auth::id(),
            'closed_at'               => $closedAt,
            'repair_duration_minutes' => $durationMinutes,
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
