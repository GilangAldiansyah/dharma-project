<?php
namespace App\Http\Controllers;

use App\Models\DiesIo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiesIoController extends Controller
{
    public function index(Request $request)
    {
        $query = DiesIo::withCount('spareparts');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama', 'like', '%' . $request->search . '%')
                  ->orWhere('cc', 'like', '%' . $request->search . '%')
                  ->orWhere('io_number', 'like', '%' . $request->search . '%');
            });
        }

        $ios        = $query->orderBy('nama')->paginate(20)->withQueryString();
        $totalCount = DiesIo::count();

        return Inertia::render('Dies/Io/Index', [
            'ios'        => $ios,
            'totalCount' => $totalCount,
            'filters'    => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'       => 'required|string|max:100',
            'cc'         => 'nullable|string|max:20',
            'io_number'  => 'nullable|string|max:20',
            'keterangan' => 'nullable|string|max:255',
        ]);

        DiesIo::create($request->only('nama', 'cc', 'io_number', 'keterangan'));

        return back()->with('success', 'IO berhasil ditambahkan.');
    }

    public function update(Request $request, DiesIo $diesIo)
    {
        $request->validate([
            'nama'       => 'required|string|max:100',
            'cc'         => 'nullable|string|max:20',
            'io_number'  => 'nullable|string|max:20',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $diesIo->update($request->only('nama', 'cc', 'io_number', 'keterangan'));

        return back()->with('success', 'IO berhasil diperbarui.');
    }

    public function destroy(DiesIo $diesIo)
    {
        if ($diesIo->spareparts()->exists()) {
            return back()->with('error', 'IO tidak dapat dihapus karena masih digunakan oleh sparepart.');
        }

        $diesIo->delete();

        return back()->with('success', 'IO berhasil dihapus.');
    }

    public function all()
    {
        return response()->json(
            DiesIo::orderBy('nama')->get(['id', 'nama', 'cc', 'io_number'])
        );
    }
}
