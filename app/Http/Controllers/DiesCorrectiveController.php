<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesCorrective;
use App\Models\DiesHistorySparepart;
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
        $query = DiesCorrective::with([
            'dies',
            'spareparts.sparepart',
            'createdBy',
            'closedBy',
        ])->latest('report_date');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('report_no', 'like', '%' . $request->search . '%')
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

        $correctives = $query->paginate(20)->withQueryString();

        $diesList = Dies::orderBy('no_part')
            ->get(['id_sap', 'no_part', 'nama_dies', 'line', 'current_stroke']);

        $spareparts = DiesSparepart::orderBy('sparepart_name')
            ->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);

        return Inertia::render('Dies/Corrective/Index', [
            'correctives' => $correctives,
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
            'problem_description'   => 'required|string',
            'cause'                 => 'nullable|string',
            'repair_action'         => 'required|string',
            'photos'                => 'nullable|array',
            'photos.*'              => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                => 'required|in:open,in_progress,closed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photos[] = $photo->store('dies/corrective', 'public');
                }
            }

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $corrective = DiesCorrective::create([
                'report_no'             => "CM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'               => $request->dies_id,
                'pic_name'              => Auth::user()->name,
                'report_date'           => $request->report_date,
                'stroke_at_maintenance' => $request->stroke_at_maintenance,
                'problem_description'   => $request->problem_description,
                'cause'                 => $request->cause,
                'repair_action'         => $request->repair_action,
                'photos'                => $photos ?: null,
                'status'                => $request->status,
                'created_by'            => Auth::id(),
                'closed_by'             => $request->status === 'closed' ? Auth::id() : null,
                'closed_at'             => $request->status === 'closed' ? now() : null,
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
            'dies_id'               => 'required|exists:dies,id_sap',
            'report_date'           => 'required|date',
            'stroke_at_maintenance' => 'required|integer|min:0',
            'problem_description'   => 'required|string',
            'cause'                 => 'nullable|string',
            'repair_action'         => 'required|string',
            'photos'                => 'nullable|array',
            'photos.*'              => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                => 'required|in:open,in_progress,closed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesCorrective) {
            $photos = $diesCorrective->photos ?? [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photos[] = $photo->store('dies/corrective', 'public');
                }
            }

            $diesCorrective->update([
                'dies_id'               => $request->dies_id,
                'report_date'           => $request->report_date,
                'stroke_at_maintenance' => $request->stroke_at_maintenance,
                'problem_description'   => $request->problem_description,
                'cause'                 => $request->cause,
                'repair_action'         => $request->repair_action,
                'photos'                => $photos ?: null,
                'status'                => $request->status,
                'closed_by'             => $request->status === 'closed' && !$diesCorrective->closed_by ? Auth::id() : $diesCorrective->closed_by,
                'closed_at'             => $request->status === 'closed' && !$diesCorrective->closed_at ? now() : $diesCorrective->closed_at,
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

        $diesCorrective->update([
            'status'    => 'closed',
            'action'    => $request->action,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
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
                    Storage::disk('public')->delete($photo);
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
            ->reject(fn($p) => $p === $request->photo)
            ->values()->all();

        Storage::disk('public')->delete($request->photo);
        $diesCorrective->update(['photos' => $photos ?: null]);

        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
