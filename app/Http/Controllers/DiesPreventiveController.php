<?php

namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesPreventive;
use App\Models\DiesHistorySparepart;
use App\Models\DiesSparepart;
use App\Models\User;
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
            'dies', 'process', 'spareparts.sparepart', 'createdBy', 'nokClosedBy',
        ])->whereNotIn('status', ['scheduled'])->latest('report_date');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('report_no',  'like', '%' . $request->search . '%')
                  ->orWhere('pic_name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('dies', fn($d) => $d->where('no_part', 'like', '%' . $request->search . '%'));
            });
        }
        if ($request->filled('status'))  $query->where('status',   $request->status);
        if ($request->filled('dies_id')) $query->where('dies_id',  $request->dies_id);

        $preventives = $query->paginate(20)->withQueryString();

        $scheduled = DiesPreventive::with(['dies.processes', 'process', 'spareparts.sparepart', 'createdBy'])
            ->where('status', 'scheduled')
            ->orderBy('scheduled_date')
            ->get();

        $diesList   = Dies::with('processes')->orderBy('no_part')
            ->get(['id_sap', 'no_part', 'nama_dies', 'line', 'current_stroke', 'std_stroke']);

        $spareparts = DiesSparepart::orderBy('sparepart_name')
            ->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);

        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');

        return Inertia::render('Dies/Preventive/Index', [
            'preventives' => $preventives,
            'scheduled'   => $scheduled,
            'diesList'    => $diesList,
            'spareparts'  => $spareparts,
            'isLeader'    => $isLeader,
            'filters'     => $request->only('search', 'status', 'dies_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dies_id'                   => 'required|exists:dies,id_sap',
            'process_id'                => 'required|exists:dies_processes,id',
            'repair_action'             => 'nullable|string',
            'photos'                    => 'nullable|array',
            'photos.*'                  => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                    => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $dies   = Dies::findOrFail($request->dies_id);
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $p) {
                    $photos[] = $p->store('dies/preventive', 'public');
                }
            }
            $yy  = now()->format('y'); $mm = now()->format('m'); $dd = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $preventive = DiesPreventive::create([
                'report_no'             => "PM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'               => $request->dies_id,
                'process_id'            => $request->process_id,
                'pic_name'              => Auth::user()->name,
                'report_date'           => now()->toDateString(),
                'stroke_at_maintenance' => $dies->current_stroke,
                'repair_action'         => $request->repair_action,
                'photos'                => $photos ?: null,
                'status'                => $request->status,
                'created_by'            => Auth::id(),
                'completed_at'          => $request->status === 'completed' ? now() : null,
            ]);

            $this->syncSpareparts($preventive, $request->spareparts ?? []);
        });

        return back()->with('success', 'Laporan preventive berhasil dibuat.');
    }

    public function complete(Request $request, DiesPreventive $diesPreventive)
    {
        abort_if($diesPreventive->status !== 'scheduled', 403, 'Hanya laporan scheduled yang bisa di-complete.');

        $request->validate([
            'photo'                     => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_sparepart'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'condition'                 => 'required|in:ok,nok',
            'repair_action'             => 'nullable|string',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesPreventive) {
            $photos   = $diesPreventive->photos ?? [];
            $photos[] = $request->file('photo')->store('dies/preventive', 'public');

            $photoSparepart = null;
            if ($request->hasFile('photo_sparepart')) {
                $photoSparepart = $request->file('photo_sparepart')->store('dies/preventive', 'public');
            }

            $diesPreventive->update([
                'repair_action' => $request->repair_action,
                'photos'        => $photos,
                'pic_dies'      => $photoSparepart,
                'condition'     => $request->condition,
                'status'        => 'completed',
                'completed_at'  => now(),
                'report_date'   => now()->toDateString(),
            ]);

            $this->syncSpareparts($diesPreventive, $request->spareparts ?? []);
        });

        return back()->with('success', 'Laporan PM berhasil diselesaikan.');
    }

    public function closeNok(Request $request, DiesPreventive $diesPreventive)
    {
        abort_if($diesPreventive->condition !== 'nok', 403, 'Hanya laporan NOK yang bisa di-close.');

        $user = User::with('roles')->find(Auth::id());
        abort_if(!($user->hasRole('leader') || $user->hasRole('admin')), 403, 'Hanya leader/admin yang bisa close NOK.');

        $request->validate(['nok_notes' => 'nullable|string']);

        $diesPreventive->update([
            'condition'     => 'ok',
            'nok_closed_by' => Auth::id(),
            'nok_closed_at' => now(),
            'nok_notes'     => $request->nok_notes,
        ]);

        return back()->with('success', 'Status PM berhasil diubah menjadi OK.');
    }

    public function update(Request $request, DiesPreventive $diesPreventive)
    {
        $request->validate([
            'dies_id'                   => 'required|exists:dies,id_sap',
            'process_id'                => 'required|exists:dies_processes,id',
            'repair_action'             => 'nullable|string',
            'photos'                    => 'nullable|array',
            'photos.*'                  => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                    => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|exists:dies_spareparts,id',
            'spareparts.*.quantity'     => 'nullable|integer|min:1',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $diesPreventive) {
            $photos = $diesPreventive->photos ?? [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $p) {
                    $photos[] = $p->store('dies/preventive', 'public');
                }
            }
            $diesPreventive->update([
                'dies_id'       => $request->dies_id,
                'process_id'    => $request->process_id,
                'repair_action' => $request->repair_action,
                'photos'        => $photos ?: null,
                'status'        => $request->status,
                'completed_at'  => $request->status === 'completed' && !$diesPreventive->completed_at
                    ? now() : $diesPreventive->completed_at,
            ]);

            $this->syncSpareparts($diesPreventive, $request->spareparts ?? []);
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
            foreach ($diesPreventive->photos ?? [] as $photo) {
                Storage::disk('public')->delete($photo);
            }
            if ($diesPreventive->pic_dies) {
                Storage::disk('public')->delete($diesPreventive->pic_dies);
            }
            $diesPreventive->delete();
        });

        return back()->with('success', 'Laporan preventive berhasil dihapus.');
    }

    public function deletePhoto(Request $request, DiesPreventive $diesPreventive)
    {
        $request->validate(['photo' => 'required|string']);
        $photos = collect($diesPreventive->photos ?? [])
            ->reject(fn($p) => $p === $request->photo)->values()->all();
        Storage::disk('public')->delete($request->photo);
        $diesPreventive->update(['photos' => $photos ?: null]);
        return back()->with('success', 'Foto berhasil dihapus.');
    }

    private function syncSpareparts(DiesPreventive $preventive, array $spareparts): void
    {
        foreach ($spareparts as $sp) {
            if (empty($sp['sparepart_id'])) continue;
            $sparepart = DiesSparepart::findOrFail($sp['sparepart_id']);
            $qty       = (int) $sp['quantity'];
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
}
