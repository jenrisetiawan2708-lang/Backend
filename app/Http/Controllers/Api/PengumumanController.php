<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\Pengguna;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    // GET /api/pengumuman  - list pengumuman (notifikasi yang broadcast)
    public function index(Request $request)
    {
        $user = $request->user();

        $pengumuman = Notifikasi::where('id_pengguna', $user->id_pengguna)
            ->whereNull('id_tagihan')   // pengumuman = notifikasi tanpa tagihan
            ->orderByDesc('tanggal_kirim')
            ->get()
            ->map(fn($n) => [
                'id'            => $n->id_notifikasi,
                'pesan'         => $n->pesan,
                'tanggal_kirim' => $n->tanggal_kirim,
                'status_baca'   => $n->status_baca,
            ]);

        return response()->json(['success' => true, 'data' => $pengumuman]);
    }

    // POST /api/pengumuman  (admin: broadcast ke semua penghuni)
    public function store(Request $request)
    {
        $request->validate([
            'pesan' => 'required|string|max:500',
        ]);

        $penghuni = Pengguna::where('role', 'penghuni')->get();

        foreach ($penghuni as $p) {
            Notifikasi::create([
                'id_pengguna'   => $p->id_pengguna,
                'id_tagihan'    => null,
                'pesan'         => $request->pesan,
                'tanggal_kirim' => now(),
                'status_baca'   => 'Belum Dibaca',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dikirim ke ' . $penghuni->count() . ' penghuni.',
        ], 201);
    }
}
