<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    // GET /api/notifikasi  - notifikasi milik user yang login
    public function index(Request $request)
    {
        $notifikasis = Notifikasi::where('id_pengguna', $request->user()->id_pengguna)
            ->orderByDesc('tanggal_kirim')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $notifikasis->map(fn($n) => [
                'id'            => $n->id_notifikasi,
                'pesan'         => $n->pesan,
                'tanggal_kirim' => $n->tanggal_kirim,
                'status_baca'   => $n->status_baca,
                'id_tagihan'    => $n->id_tagihan,
            ]),
            'unread_count' => $notifikasis->where('status_baca', 'Belum Dibaca')->count(),
        ]);
    }

    // PUT /api/notifikasi/{id}/baca
    public function markRead(Request $request, $id)
    {
        $notif = Notifikasi::where('id_notifikasi', $id)
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->firstOrFail();

        $notif->update(['status_baca' => 'Dibaca']);
        return response()->json(['success' => true, 'message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    // PUT /api/notifikasi/baca-semua
    public function markAllRead(Request $request)
    {
        Notifikasi::where('id_pengguna', $request->user()->id_pengguna)
            ->where('status_baca', 'Belum Dibaca')
            ->update(['status_baca' => 'Dibaca']);

        return response()->json(['success' => true, 'message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
