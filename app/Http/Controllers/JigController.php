<?php

namespace App\Http\Controllers;

use App\Models\Jig;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JigController extends Controller
{
    public function index(Request $request)
    {
        $query = Jig::with('pic:id,name');

        if ($request->kategori) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->filled('line')) {
            $query->where('line', 'like', '%' . $request->line . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('type', 'like', '%' . $request->search . '%');
            });
        }

        $jigs  = $query->latest()->get();
        $users = User::whereHas('roles', fn($q) => $q->where('name', 'pic_jig'))
                     ->select('id', 'name')
                     ->orderBy('name')
                     ->get();

        $lines = Jig::select('line')->distinct()->orderBy('line')->pluck('line');

        return Inertia::render('Jig/Index', [
            'jigs'    => $jigs,
            'users'   => $users,
            'lines'   => $lines,
            'filters' => $request->only('kategori', 'line', 'search'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'     => 'required|string|max:100',
            'name'     => 'required|string|max:255',
            'kategori' => 'required|in:regular,slow_moving,discontinue',
            'line'     => 'required|string|max:100',
            'pic_id'   => 'required|exists:users,id',
        ]);

        Jig::create($request->only('type', 'name', 'kategori', 'line', 'pic_id'));

        return back()->with('success', 'Jig berhasil ditambahkan.');
    }

    public function update(Request $request, Jig $jig)
    {
        $request->validate([
            'type'     => 'required|string|max:100',
            'name'     => 'required|string|max:255',
            'kategori' => 'required|in:regular,slow_moving,discontinue',
            'line'     => 'required|string|max:100',
            'pic_id'   => 'required|exists:users,id',
        ]);

        $jig->update($request->only('type', 'name', 'kategori', 'line', 'pic_id'));

        return back()->with('success', 'Jig berhasil diupdate.');
    }

    public function destroy(Jig $jig)
    {
        $jig->delete();

        return back()->with('success', 'Jig berhasil dihapus.');
    }
}
