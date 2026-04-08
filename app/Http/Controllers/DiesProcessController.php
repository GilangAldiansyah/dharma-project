<?php
namespace App\Http\Controllers;
use App\Models\Dies;
use App\Models\DiesProcess;
use Illuminate\Http\Request;

class DiesProcessController extends Controller
{
    public function store(Request $request, string $idSap)
    {
        $dies = Dies::findOrFail($idSap);
        $request->validate([
            'process_name'   => 'required|string|max:100',
            'no_proses'      => 'nullable|integer|min:0',
            'tonase'         => 'nullable|numeric|min:0',
            'std_stroke'     => 'required|integer|min:0',
            'current_stroke' => 'nullable|integer|min:0',
            'last_mtc_date'  => 'nullable|date',
        ]);

        $maxNo = $dies->processes()->max('no_proses') ?? -1;

        $dies->processes()->create([
            'process_name'   => $request->process_name,
            'no_proses'      => $request->no_proses ?? $maxNo + 1,
            'tonase'         => $request->tonase ?: null,
            'std_stroke'     => $request->std_stroke,
            'current_stroke' => $request->current_stroke ?? 0,
            'last_mtc_date'  => $request->last_mtc_date ?: null,
        ]);

        return redirect()->route('dies.show', ['idSap' => $idSap])
            ->with('success', 'Proses berhasil ditambahkan.');
    }

    public function update(Request $request, string $idSap, DiesProcess $process)
    {
        $request->validate([
            'process_name'   => 'required|string|max:100',
            'no_proses'      => 'nullable|integer|min:0',
            'tonase'         => 'nullable|numeric|min:0',
            'std_stroke'     => 'required|integer|min:0',
            'current_stroke' => 'nullable|integer|min:0',
            'last_mtc_date'  => 'nullable|date',
        ]);

        $process->update([
            'process_name'   => $request->process_name,
            'no_proses'      => $request->no_proses ?? $process->no_proses,
            'tonase'         => $request->tonase ?: null,
            'std_stroke'     => $request->std_stroke,
            'current_stroke' => $request->current_stroke ?? $process->current_stroke,
            'last_mtc_date'  => $request->last_mtc_date ?: null,
        ]);

        return redirect()->route('dies.show', ['idSap' => $idSap])
            ->with('success', 'Proses berhasil diperbarui.');
    }

    public function reorder(Request $request, string $idSap)
    {
        $request->validate([
            'orders'   => 'required|array',
            'orders.*' => 'integer',
        ]);

        foreach ($request->orders as $noProses => $processId) {
            DiesProcess::where('id', $processId)
                ->whereHas('dies', fn($q) => $q->where('id_sap', $idSap))
                ->update(['no_proses' => $noProses]);
        }

        return back()->with('success', 'Urutan proses berhasil disimpan.');
    }

    public function destroy(string $idSap, DiesProcess $process)
    {
        $process->delete();
        return redirect()->route('dies.show', ['idSap' => $idSap])
            ->with('success', 'Proses berhasil dihapus.');
    }
}
