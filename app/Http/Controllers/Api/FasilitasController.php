<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use Illuminate\Http\Request;

class FasilitasController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => Fasilitas::orderBy('nama_fasilitas')->get()->map(fn($f) => ['id' => $f->id_fasilitas, 'nama' => $f->nama_fasilitas]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['nama_fasilitas' => 'required|string|max:50|unique:fasilitas,nama_fasilitas']);
        $f = Fasilitas::create(['nama_fasilitas' => $request->nama_fasilitas]);
        return response()->json(['success' => true, 'data' => ['id' => $f->id_fasilitas, 'nama' => $f->nama_fasilitas]], 201);
    }

    public function destroy($id)
    {
        Fasilitas::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Fasilitas dihapus.']);
    }
}
