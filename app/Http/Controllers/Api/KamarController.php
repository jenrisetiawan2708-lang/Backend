<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kamar;
use App\Models\Fasilitas;
use Illuminate\Http\Request;

class KamarController extends Controller
{
    // GET /api/kamar  - daftar semua kamar
    public function index(Request $request)
    {
        $query = Kamar::with('fasilitas');

        if ($request->has('status')) {
            $query->where('status_kamar', $request->status);
        }

        $kamars = $query->orderBy('nomor_kamar')->get();

        return response()->json([
            'success' => true,
            'data'    => $kamars->map(fn($k) => $this->formatKamar($k)),
        ]);
    }

    // GET /api/kamar/{id}
    public function show($id)
    {
        $kamar = Kamar::with('fasilitas')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->formatKamar($kamar)]);
    }

    // POST /api/kamar  (admin only)
    public function store(Request $request)
    {
        $request->validate([
            'nomor_kamar'  => 'required|string|max:10|unique:kamar,nomor_kamar',
            'harga'        => 'required|numeric|min:0',
            'status_kamar' => 'required|in:kosong,terisi',
            'fasilitas'    => 'array',
            'fasilitas.*'  => 'integer|exists:fasilitas,id_fasilitas',
        ]);

        $kamar = Kamar::create([
            'nomor_kamar'  => $request->nomor_kamar,
            'harga'        => $request->harga,
            'status_kamar' => $request->status_kamar,
        ]);

        if ($request->has('fasilitas')) {
            $kamar->fasilitas()->sync($request->fasilitas);
        }

        return response()->json(['success' => true, 'message' => 'Kamar berhasil ditambahkan.', 'data' => $this->formatKamar($kamar->load('fasilitas'))], 201);
    }

    // PUT /api/kamar/{id}
    public function update(Request $request, $id)
    {
        $kamar = Kamar::findOrFail($id);

        $request->validate([
            'nomor_kamar'  => 'sometimes|string|max:10|unique:kamar,nomor_kamar,' . $id . ',id_kamar',
            'harga'        => 'sometimes|numeric|min:0',
            'status_kamar' => 'sometimes|in:kosong,terisi',
            'fasilitas'    => 'array',
            'fasilitas.*'  => 'integer|exists:fasilitas,id_fasilitas',
        ]);

        $kamar->update($request->only(['nomor_kamar', 'harga', 'status_kamar']));

        if ($request->has('fasilitas')) {
            $kamar->fasilitas()->sync($request->fasilitas);
        }

        return response()->json(['success' => true, 'message' => 'Kamar diperbarui.', 'data' => $this->formatKamar($kamar->load('fasilitas'))]);
    }

    // DELETE /api/kamar/{id}
    public function destroy($id)
    {
        $kamar = Kamar::findOrFail($id);
        $kamar->delete();
        return response()->json(['success' => true, 'message' => 'Kamar dihapus.']);
    }

    // GET /api/kamar/summary  - ringkasan untuk dashboard admin
    public function summary()
    {
        $total  = Kamar::count();
        $terisi = Kamar::where('status_kamar', 'terisi')->count();
        $kosong = Kamar::where('status_kamar', 'kosong')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total'  => $total,
                'terisi' => $terisi,
                'kosong' => $kosong,
            ],
        ]);
    }

    private function formatKamar(Kamar $k): array
    {
        return [
            'id'           => $k->id_kamar,
            'nomor_kamar'  => $k->nomor_kamar,
            'harga'        => $k->harga,
            'harga_format' => 'Rp ' . number_format($k->harga, 0, ',', '.'),
            'status_kamar' => $k->status_kamar,
            'fasilitas'    => $k->fasilitas->map(fn($f) => ['id' => $f->id_fasilitas, 'nama' => $f->nama_fasilitas]),
        ];
    }
}
