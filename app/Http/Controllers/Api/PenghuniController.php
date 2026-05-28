<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Models\SewaKamar;
use App\Models\Kamar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PenghuniController extends Controller
{
    // GET /api/penghuni
    public function index(Request $request)
    {
        $query = Pengguna::where('role', 'penghuni')->with(['sewaKamar.kamar']);

        if ($request->has('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('username', 'like', "%$s%");
            });
        }

        $penghuni = $query->orderBy('nama')->get();

        return response()->json([
            'success' => true,
            'data'    => $penghuni->map(fn($p) => $this->formatPenghuni($p)),
        ]);
    }

    // GET /api/penghuni/{id}
    public function show($id)
    {
        $penghuni = Pengguna::with(['sewaKamar.kamar', 'notifikasi'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->formatPenghuni($penghuni)]);
    }

    // POST /api/penghuni  (admin: tambah penghuni baru)
    public function store(Request $request)
    {
        $request->validate([
            'nama'       => 'required|string|max:100',
            'email'      => 'required|email|unique:pengguna,email',
            'username'   => 'required|string|max:100|unique:pengguna,username',
            'password'   => 'required|string|min:8',
            'id_kamar'   => 'required|integer|exists:kamar,id_kamar',
            'tanggal_masuk' => 'required|date',
        ]);

        $penghuni = Pengguna::create([
            'nama'     => $request->nama,
            'email'    => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role'     => 'penghuni',
        ]);

        // Buat sewa kamar
        SewaKamar::create([
            'id_pengguna'   => $penghuni->id_pengguna,
            'id_kamar'      => $request->id_kamar,
            'tanggal_masuk' => $request->tanggal_masuk,
            'status_sewa'   => 'aktif',
        ]);

        // Update status kamar
        Kamar::where('id_kamar', $request->id_kamar)->update(['status_kamar' => 'terisi']);

        return response()->json(['success' => true, 'message' => 'Penghuni berhasil ditambahkan.', 'data' => $this->formatPenghuni($penghuni->load('sewaKamar.kamar'))], 201);
    }

    // PUT /api/penghuni/{id}
    public function update(Request $request, $id)
    {
        $penghuni = Pengguna::findOrFail($id);

        $request->validate([
            'nama'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:pengguna,email,' . $id . ',id_pengguna',
            'username' => 'sometimes|string|max:100|unique:pengguna,username,' . $id . ',id_pengguna',
            'password' => 'sometimes|string|min:8',
        ]);

        $data = $request->only(['nama', 'email', 'username']);
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $penghuni->update($data);

        return response()->json(['success' => true, 'message' => 'Data penghuni diperbarui.', 'data' => $this->formatPenghuni($penghuni)]);
    }

    // DELETE /api/penghuni/{id}  (admin: nonaktifkan)
    public function destroy($id)
    {
        $penghuni = Pengguna::findOrFail($id);

        // Selesaikan sewa aktif
        $sewa = SewaKamar::where('id_pengguna', $id)->where('status_sewa', 'aktif')->first();
        if ($sewa) {
            $sewa->update(['status_sewa' => 'selesai', 'tanggal_keluar' => now()->toDateString()]);
            Kamar::where('id_kamar', $sewa->id_kamar)->update(['status_kamar' => 'kosong']);
        }

        $penghuni->delete();

        return response()->json(['success' => true, 'message' => 'Penghuni dihapus.']);
    }

    // GET /api/penghuni/me/profile  (penghuni: profil sendiri)
    public function myProfile(Request $request)
    {
        $penghuni = Pengguna::with(['sewaKamar' => fn($q) => $q->where('status_sewa', 'aktif')->with('kamar'), 'notifikasi' => fn($q) => $q->orderByDesc('tanggal_kirim')->limit(10)])
            ->findOrFail($request->user()->id_pengguna);

        return response()->json(['success' => true, 'data' => $this->formatPenghuni($penghuni)]);
    }

    private function formatPenghuni(Pengguna $p): array
    {
        $sewaAktif = $p->sewaKamar->firstWhere('status_sewa', 'aktif');

        return [
            'id'          => $p->id_pengguna,
            'nama'        => $p->nama,
            'email'       => $p->email,
            'username'    => $p->username,
            'role'        => $p->role,
            'kamar'       => $sewaAktif ? [
                'id_sewa'       => $sewaAktif->id_sewa,
                'nomor_kamar'   => $sewaAktif->kamar?->nomor_kamar,
                'harga'         => $sewaAktif->kamar?->harga,
                'tanggal_masuk' => $sewaAktif->tanggal_masuk,
                'status_sewa'   => $sewaAktif->status_sewa,
            ] : null,
        ];
    }
}
