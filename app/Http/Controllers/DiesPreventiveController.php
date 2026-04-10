<?php
namespace App\Http\Controllers;

use App\Models\Dies;
use App\Models\DiesIo;
use App\Models\DiesProcess;
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
        $baseQuery = DiesPreventive::query();

        if ($request->filled('search')) {
            $baseQuery->where(function ($q) use ($request) {
                $q->where('report_no',  'like', '%' . $request->search . '%')
                  ->orWhere('pic_name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('dies', fn($d) => $d->where('no_part', 'like', '%' . $request->search . '%'));
            });
        }
        if ($request->filled('status'))    $baseQuery->where('status', $request->status);
        if ($request->filled('dies_id'))   $baseQuery->where('dies_id', $request->dies_id);
        if ($request->filled('date_from')) $baseQuery->whereDate('report_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $baseQuery->whereDate('report_date', '<=', $request->date_to);

        $totalCount  = (clone $baseQuery)->count();

        $preventives = (clone $baseQuery)
            ->with(['dies', 'process', 'spareparts.sparepart', 'createdBy', 'nokClosedBy'])
            ->latest('report_date')
            ->paginate(20)
            ->withQueryString();

        $nearProcesses = DiesProcess::with('dies')
            ->where('std_stroke', '>', 0)
            ->whereRaw('(current_stroke / std_stroke * 100) >= 86')
            ->orderByRaw('(current_stroke / std_stroke) DESC')
            ->get()
            ->map(function ($proc) {
                $pct = round($proc->current_stroke / $proc->std_stroke * 100, 1);
                return [
                    'process_id'     => $proc->id,
                    'process_name'   => $proc->process_name,
                    'dies_id'        => $proc->dies_id,
                    'dies'           => $proc->dies,
                    'std_stroke'     => $proc->std_stroke,
                    'current_stroke' => $proc->current_stroke,
                    'remaining'      => max(0, $proc->std_stroke - $proc->current_stroke),
                    'pct'            => $pct,
                    'urgency'        => $pct >= 96 ? 'urgent' : 'scheduled',
                    'last_mtc_date'  => $proc->last_mtc_date,
                ];
            });

        $diesList   = Dies::with('processes')->orderBy('no_part')->get(['id_sap', 'no_part', 'nama_dies', 'line']);
        $spareparts = DiesSparepart::orderBy('sparepart_name')->get(['id', 'sparepart_code', 'sparepart_name', 'unit', 'stok']);
        $ios        = DiesIo::orderBy('nama')->get(['id', 'nama', 'cc', 'io_number']);

        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');

        return Inertia::render('Dies/Preventive/Index', [
            'preventives'   => $preventives,
            'totalCount'    => $totalCount,
            'nearProcesses' => $nearProcesses,
            'diesList'      => $diesList,
            'spareparts'    => $spareparts,
            'ios'           => $ios,
            'isLeader'      => $isLeader,
            'filters'       => $request->only('search', 'status', 'dies_id', 'date_from', 'date_to'),
        ]);
    }

    public function submitFromDies(Request $request)
    {
        $validated = $request->validate([
            'process_id'                => 'required|exists:dies_processes,id',
            'photo'                     => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_sparepart'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'condition'                 => 'required|in:ok,nok',
            'repair_action'             => 'nullable|string',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|integer|exists:dies_spareparts,id',
            'spareparts.*.io_id'        => 'required_with:spareparts.*.sparepart_id|exists:dies_io,id',
            'spareparts.*.quantity'     => 'nullable|string',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $process = DiesProcess::with('dies')->findOrFail($validated['process_id']);
            $dies    = $process->dies;

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $photo          = $request->file('photo')->store('dies/preventive', 'public');
            $photoSparepart = $request->hasFile('photo_sparepart')
                ? $request->file('photo_sparepart')->store('dies/preventive', 'public')
                : null;

            $preventive = DiesPreventive::create([
                'report_no'             => "PM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'               => $dies->id_sap,
                'process_id'            => $process->id,
                'pic_name'              => Auth::user()->name,
                'report_date'           => now()->toDateString(),
                'stroke_at_maintenance' => $process->current_stroke,
                'repair_action'         => $validated['repair_action'] ?? null,
                'photos'                => [$photo],
                'pic_dies'              => $photoSparepart,
                'condition'             => $validated['condition'],
                'status'                => 'completed',
                'completed_at'          => now(),
                'created_by'            => Auth::id(),
            ]);

            $process->update([
                'current_stroke' => 0,
                'last_mtc_date'  => now()->toDateString(),
            ]);

            $this->syncSpareparts($preventive, $validated['spareparts'] ?? []);
        });

        return back()->with('success', 'PM berhasil disubmit. Stroke proses direset ke 0.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dies_id'                   => 'required|exists:dies,id_sap',
            'process_id'                => 'required|exists:dies_processes,id',
            'repair_action'             => 'nullable|string',
            'photos'                    => 'nullable|array',
            'photos.*'                  => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                    => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|integer|exists:dies_spareparts,id',
            'spareparts.*.io_id'        => 'required_with:spareparts.*.sparepart_id|exists:dies_io,id',
            'spareparts.*.quantity'     => 'nullable|string',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $process = DiesProcess::findOrFail($validated['process_id']);

            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $p) {
                    $photos[] = $p->store('dies/preventive', 'public');
                }
            }

            $yy  = now()->format('y');
            $mm  = now()->format('m');
            $dd  = now()->format('d');
            $rnd = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);

            $preventive = DiesPreventive::create([
                'report_no'             => "PM-DIES-{$yy}{$mm}{$dd}-{$rnd}",
                'dies_id'               => $validated['dies_id'],
                'process_id'            => $validated['process_id'],
                'pic_name'              => Auth::user()->name,
                'report_date'           => now()->toDateString(),
                'stroke_at_maintenance' => $process->current_stroke,
                'repair_action'         => $validated['repair_action'] ?? null,
                'photos'                => $photos ?: null,
                'status'                => $validated['status'],
                'created_by'            => Auth::id(),
                'completed_at'          => $validated['status'] === 'completed' ? now() : null,
            ]);

            if ($validated['status'] === 'completed') {
                $process->update([
                    'current_stroke' => 0,
                    'last_mtc_date'  => now()->toDateString(),
                ]);
            }

            $this->syncSpareparts($preventive, $validated['spareparts'] ?? []);
        });

        return back()->with('success', 'Laporan preventive berhasil dibuat.');
    }

    public function closeNok(Request $request, DiesPreventive $diesPreventive)
    {
        abort_if($diesPreventive->condition !== 'nok', 403);

        $user = User::with('roles')->find(Auth::id());
        abort_if(!($user->hasRole('leader') || $user->hasRole('admin')), 403);

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
        $validated = $request->validate([
            'dies_id'                   => 'required|exists:dies,id_sap',
            'process_id'                => 'required|exists:dies_processes,id',
            'repair_action'             => 'nullable|string',
            'photos'                    => 'nullable|array',
            'photos.*'                  => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'status'                    => 'required|in:pending,in_progress,completed',
            'spareparts'                => 'nullable|array',
            'spareparts.*.sparepart_id' => 'nullable|integer|exists:dies_spareparts,id',
            'spareparts.*.io_id'        => 'required_with:spareparts.*.sparepart_id|exists:dies_io,id',
            'spareparts.*.quantity'     => 'nullable|string',
            'spareparts.*.notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated, $diesPreventive) {
            $photos = $diesPreventive->photos ?? [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $p) {
                    $photos[] = $p->store('dies/preventive', 'public');
                }
            }

            $wasCompleted = $diesPreventive->status === 'completed';
            $nowCompleted = $validated['status'] === 'completed';

            $diesPreventive->update([
                'dies_id'       => $validated['dies_id'],
                'process_id'    => $validated['process_id'],
                'repair_action' => $validated['repair_action'] ?? null,
                'photos'        => $photos ?: null,
                'status'        => $validated['status'],
                'completed_at'  => $nowCompleted && !$diesPreventive->completed_at
                    ? now() : $diesPreventive->completed_at,
            ]);

            if (!$wasCompleted && $nowCompleted) {
                DiesProcess::where('id', $validated['process_id'])->update([
                    'current_stroke' => 0,
                    'last_mtc_date'  => now()->toDateString(),
                ]);
            }

            $this->syncSpareparts($diesPreventive, $validated['spareparts'] ?? []);
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
            $sparepartId = isset($sp['sparepart_id']) ? (int) $sp['sparepart_id'] : 0;
            if ($sparepartId <= 0) continue;

            $qty = isset($sp['quantity']) ? (int) trim((string) $sp['quantity']) : 0;
            if ($qty <= 0) continue;

            $sparepart = DiesSparepart::findOrFail($sparepartId);

            if ($sparepart->stok < $qty) {
                throw new \Exception(
                    "Stok {$sparepart->sparepart_name} tidak mencukupi (tersedia: {$sparepart->stok}, diminta: {$qty})."
                );
            }

            DiesHistorySparepart::create([
                'tipe'           => 'preventive',
                'maintenance_id' => $preventive->id,
                'sparepart_id'   => $sparepartId,
                'io_id'          => $sp['io_id'] ?? null,
                'quantity'       => $qty,
                'notes'          => isset($sp['notes']) && trim($sp['notes']) !== '' ? trim($sp['notes']) : null,
                'created_by'     => Auth::id(),
            ]);

            $sparepart->decrement('stok', $qty);
        }
    }
}
