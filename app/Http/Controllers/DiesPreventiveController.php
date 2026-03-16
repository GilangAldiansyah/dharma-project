<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesPreventive;
use App\Models\DiesHistorySparepart;
use App\Models\DiesSparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DiesPreventiveController extends Controller
{
    public function index(Request $request)
    {
        $query = DiesPreventive::with([
            'dies',
            'spareparts.sparepart',
            'createdBy',
        ])->latest('report_date');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('report_no',  'like', '%' . $request->search . '%')
                  ->orWhere('pic_name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('dies', fn($d) => $d->where('no_part', 'like', '%' . $request->search . '%'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('dies_id')) {
            $query->where('dies_id', $request->dies_id);
        }

        $preventives = $query->paginate(20)->withQueryString();

        $diesList = Dies::orderBy('no_part')
            ->get(['id_sap', 'no_part', 'nama_dies', 'line', 'current_stroke', 'std_stroke']);

        $spareparts = DiesSparepart::orderBy('sparepart_name')
            ->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);

        return Inertia::render('Dies/Preventive/Index', [
            'preventives' => $preventives,
            'diesList'    => $diesList,
            'spareparts'  => $spareparts,
            'filters'     => $request->only('search', 'status', 'dies_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dies_id'               => 'required|exists:dies,id_sap',
            'report_date'           => 'required|date',
            'stroke_at_maintenance' => 'required|integer|min:0',
            'repair_process'        => 'nullable|string',
            'repair_action'         => 'nullable|string',
            'photos'                => 'nullable|array',
            'photos.*'              => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photos[] = $photo->store('dies/preventive', 'public');
                }
            }

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $preventive = DiesPreventive::create([
                'report_no'             => "PM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'               => $request->dies_id,
                'pic_name'              => Auth::user()->name,
                'report_date'           => $request->report_date,
                'stroke_at_maintenance' => $request->stroke_at_maintenance,
                'repair_process'        => $request->repair_process,
                'repair_action'         => $request->repair_action,
                'photos'                => $photos ?: null,
                'status'                => $request->status,
                'created_by'            => Auth::id(),
                'completed_at'          => $request->status === 'completed' ? now() : null,
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
                        'tipe'           => 'preventive',
                        'maintenance_id' => $preventive->id,
                        'sparepart_id'   => $sp['sparepart_id'],
                        'quantity'       => $qty,
                        'notes'          => $sp['notes'] ?? null,
                        'created_by'     => Auth::id(),
                    ]);
                    $sparepart->decrement('stok', $qty);
                }
            }
        });

        return back()->with('success', 'Laporan preventive berhasil dibuat.');
    }

    public function update(Request $request, DiesPreventive $diesPreventive)
    {
        $request->validate([
            'dies_id'               => 'required|exists:dies,id_sap',
            'report_date'           => 'required|date',
            'stroke_at_maintenance' => 'required|integer|min:0',
            'repair_process'        => 'nullable|string',
            'repair_action'         => 'nullable|string',
            'photos'                => 'nullable|array',
            'photos.*'              => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesPreventive) {
            $photos = $diesPreventive->photos ?? [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photos[] = $photo->store('dies/preventive', 'public');
                }
            }

            $diesPreventive->update([
                'dies_id'               => $request->dies_id,
                'report_date'           => $request->report_date,
                'stroke_at_maintenance' => $request->stroke_at_maintenance,
                'repair_process'        => $request->repair_process,
                'repair_action'         => $request->repair_action,
                'photos'                => $photos ?: null,
                'status'                => $request->status,
                'completed_at'          => $request->status === 'completed' && !$diesPreventive->completed_at ? now() : $diesPreventive->completed_at,
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
                        'tipe'           => 'preventive',
                        'maintenance_id' => $diesPreventive->id,
                        'sparepart_id'   => $sp['sparepart_id'],
                        'quantity'       => $qty,
                        'notes'          => $sp['notes'] ?? null,
                        'created_by'     => Auth::id(),
                    ]);
                    $sparepart->decrement('stok', $qty);
                }
            }
        });

        return back()->with('success', 'Laporan preventive berhasil diperbarui.');
    }

    public function destroy(DiesPreventive $diesPreventive)
    {
        DB::transaction(function () use ($diesPreventive) {
            foreach ($diesPreventive->spareparts as $history) {
                $history->sparepart?->increment('stok', $history->quantity);
                $history->delete();
            }
            if ($diesPreventive->photos) {
                foreach ($diesPreventive->photos as $photo) {
                    Storage::disk('public')->delete($photo);
                }
            }
            $diesPreventive->delete();
        });

        return back()->with('success', 'Laporan preventive berhasil dihapus.');
    }

    public function deletePhoto(Request $request, DiesPreventive $diesPreventive)
    {
        $request->validate(['photo' => 'required|string']);
        $photos = collect($diesPreventive->photos ?? [])
            ->reject(fn($p) => $p === $request->photo)
            ->values()->all();
        Storage::disk('public')->delete($request->photo);
        $diesPreventive->update(['photos' => $photos ?: null]);
        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
