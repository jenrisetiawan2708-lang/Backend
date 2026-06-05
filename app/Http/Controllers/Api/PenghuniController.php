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
            'id_kamar' => 'sometimes|nullable|integer|exists:kamar,id_kamar',
            'status'   => 'sometimes|in:aktif,non-aktif',
        ]);

        // Map frontend status ke nilai DB: 'non-aktif' -> 'selesai', 'aktif' -> 'aktif'
        $statusSewa = $request->status === 'non-aktif' ? 'selesai' : 'aktif';

        // Update data pengguna dasar
        $data = $request->only(['nama', 'email', 'username']);
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }
        if (!empty($data)) {
            $penghuni->update($data);
        }

        // Update kamar & status sewa jika dikirim
        if ($request->has('id_kamar') || $request->has('status')) {
            $sewaLama = SewaKamar::where('id_pengguna', $id)->where('status_sewa', 'aktif')->first();
            $idKamarBaru = $request->has('id_kamar') ? $request->id_kamar : ($sewaLama?->id_kamar);
            $statusBaru  = $request->has('status') ? $statusSewa : 'aktif';

            if ($sewaLama) {
                $kamarBerubah = $idKamarBaru && $idKamarBaru != $sewaLama->id_kamar;

                if ($kamarBerubah) {
                    // Kosongkan kamar lama
                    Kamar::where('id_kamar', $sewaLama->id_kamar)->update(['status_kamar' => 'kosong']);
                    // Selesaikan sewa lama
                    $sewaLama->update(['status_sewa' => 'selesai', 'tanggal_keluar' => now()->toDateString()]);
                    // Buat sewa baru
                    SewaKamar::create([
                        'id_pengguna'   => $id,
                        'id_kamar'      => $idKamarBaru,
                        'tanggal_masuk' => now()->toDateString(),
                        'status_sewa'   => $statusBaru,
                    ]);
                    // Tandai kamar baru terisi
                    Kamar::where('id_kamar', $idKamarBaru)->update(['status_kamar' => 'terisi']);
                } else {
                    // Kamar sama, hanya update status sewa
                    $sewaLama->update(['status_sewa' => $statusBaru]);
                    // Sinkron status kamar: aktif -> terisi, selesai -> kosong
                    if ($idKamarBaru) {
                        Kamar::where('id_kamar', $idKamarBaru)->update([
                            'status_kamar' => $statusBaru === 'aktif' ? 'terisi' : 'kosong',
                        ]);
                    }
                }
            } else {
                // Belum punya sewa aktif — buat sewa baru jika ada kamar dipilih
                if ($idKamarBaru) {
                    SewaKamar::create([
                        'id_pengguna'   => $id,
                        'id_kamar'      => $idKamarBaru,
                        'tanggal_masuk' => now()->toDateString(),
                        'status_sewa'   => $statusBaru,
                    ]);
                    Kamar::where('id_kamar', $idKamarBaru)->update(['status_kamar' => 'terisi']);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Data penghuni diperbarui.', 'data' => $this->formatPenghuni($penghuni->load('sewaKamar.kamar'))]);
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
                'id'            => $sewaAktif->kamar?->id_kamar,
                'id_sewa'       => $sewaAktif->id_sewa,
                'nomor_kamar'   => $sewaAktif->kamar?->nomor_kamar,
                'harga'         => $sewaAktif->kamar?->harga,
                'tanggal_masuk' => $sewaAktif->tanggal_masuk,
                'status_sewa'   => $sewaAktif->status_sewa,
            ] : null,
        ];
    }
}
