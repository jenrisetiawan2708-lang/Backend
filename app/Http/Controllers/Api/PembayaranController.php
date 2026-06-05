<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PembayaranController extends Controller
{
    // GET /api/pembayaran  (admin: semua | penghuni: milik sendiri)
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'owner') {
            $pembayarans = Pembayaran::with(['tagihan.sewaKamar.pengguna', 'tagihan.sewaKamar.kamar'])
                ->orderByDesc('tanggal_pembayaran')
                ->get();
        } else {
            $pembayarans = Pembayaran::with(['tagihan.sewaKamar.pengguna', 'tagihan.sewaKamar.kamar'])
                ->whereHas('tagihan.sewaKamar', fn($q) => $q->where('id_pengguna', $user->id_pengguna))
                ->orderByDesc('tanggal_pembayaran')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $pembayarans->map(fn($p) => $this->formatPembayaran($p)),
        ]);
    }

    // POST /api/pembayaran  (penghuni: upload bukti pembayaran manual)
    public function store(Request $request)
    {
        $request->validate([
            'id_tagihan'          => 'required|integer|exists:tagihan,id_tagihan',
            'jumlah_bayar'        => 'required|numeric|min:0',
            'bukti'               => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'tanggal_pembayaran'  => 'required|date',
        ]);

        $tagihan = Tagihan::findOrFail($request->id_tagihan);
        if ($tagihan->status_tagihan === 'Lunas') {
            return response()->json(['success' => false, 'message' => 'Tagihan ini sudah lunas.'], 422);
        }

        // Simpan file bukti
        $buktiPath = $request->file('bukti')->store('bukti-pembayaran', 'public');

        $pembayaran = Pembayaran::create([
            'id_tagihan'          => $request->id_tagihan,
            'tanggal_pembayaran'  => $request->tanggal_pembayaran,
            'jumlah_bayar'        => $request->jumlah_bayar,
            'bukti'               => $buktiPath,
            'status_validasi'     => 'Menunggu Validasi',
        ]);

        // Notifikasi ke admin (owner) - buat notif untuk owner
        $owner = \App\Models\Pengguna::where('role', 'owner')->first();
        if ($owner) {
            Notifikasi::create([
                'id_pengguna'   => $owner->id_pengguna,
                'id_tagihan'    => $request->id_tagihan,
                'pesan'         => 'Pembayaran baru menunggu validasi dari ' . $request->user()->nama,
                'tanggal_kirim' => now(),
                'status_baca'   => 'Belum Dibaca',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Bukti pembayaran berhasil diunggah. Menunggu validasi admin.', 'data' => $this->formatPembayaran($pembayaran)], 201);
    }

    // PUT /api/pembayaran/{id}/validasi  (admin: validasi / tolak)
    public function validasi(Request $request, $id)
    {
        $pembayaran = Pembayaran::with('tagihan')->findOrFail($id);

        $request->validate([
            'status' => 'required|in:Valid,Ditolak',
            'catatan' => 'nullable|string',
        ]);

        $pembayaran->update(['status_validasi' => $request->status]);

        if ($request->status === 'Valid') {
            $pembayaran->tagihan->update(['status_tagihan' => 'Lunas']);

            // Aktifkan kembali sewa & kamar jika sebelumnya non-aktif (selesai)
            $sewa = $pembayaran->tagihan->sewaKamar;
            if ($sewa && $sewa->status_sewa === 'selesai') {
                $sewa->update(['status_sewa' => 'aktif', 'tanggal_keluar' => null]);
                \App\Models\Kamar::where('id_kamar', $sewa->id_kamar)
                    ->update(['status_kamar' => 'terisi']);
            }

            // Notifikasi ke penghuni
            if ($sewa) {
                Notifikasi::create([
                    'id_pengguna'   => $sewa->id_pengguna,
                    'id_tagihan'    => $pembayaran->id_tagihan,
                    'pesan'         => 'Pembayaran Anda telah divalidasi dan tagihan dinyatakan LUNAS. Status penghuni kembali Aktif.',
                    'tanggal_kirim' => now(),
                    'status_baca'   => 'Belum Dibaca',
                ]);
            }
        } elseif ($request->status === 'Ditolak') {
            $sewa = $pembayaran->tagihan->sewaKamar;
            if ($sewa) {
                $catatan = $request->catatan ? " Catatan: {$request->catatan}" : '';
                Notifikasi::create([
                    'id_pengguna'   => $sewa->id_pengguna,
                    'id_tagihan'    => $pembayaran->id_tagihan,
                    'pesan'         => 'Pembayaran Anda ditolak. Silakan unggah ulang bukti pembayaran.' . $catatan,
                    'tanggal_kirim' => now(),
                    'status_baca'   => 'Belum Dibaca',
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Status pembayaran diperbarui.', 'data' => $this->formatPembayaran($pembayaran)]);
    }

    // GET /api/pembayaran/menunggu  (admin: daftar yang menunggu validasi)
    public function menungguValidasi()
    {
        $pembayarans = Pembayaran::with(['tagihan.sewaKamar.pengguna', 'tagihan.sewaKamar.kamar'])
            ->where('status_validasi', 'Menunggu Validasi')
            ->orderByDesc('tanggal_pembayaran')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $pembayarans->map(fn($p) => $this->formatPembayaran($p)),
        ]);
    }

    private function formatPembayaran(Pembayaran $p): array
    {
        return [
            'id'                  => $p->id_pembayaran,
            'id_tagihan'          => $p->id_tagihan,
            'tanggal_pembayaran'  => $p->tanggal_pembayaran,
            'jumlah_bayar'        => $p->jumlah_bayar,
            'jumlah_format'       => 'Rp ' . number_format($p->jumlah_bayar, 0, ',', '.'),
            'bukti'               => $p->bukti ? asset('storage/' . $p->bukti) : null,
            'status_validasi'     => $p->status_validasi,
            'penghuni'            => $p->tagihan?->sewaKamar?->pengguna ? ['nama' => $p->tagihan->sewaKamar->pengguna->nama] : null,
            'kamar'               => $p->tagihan?->sewaKamar?->kamar ? ['nomor' => $p->tagihan->sewaKamar->kamar->nomor_kamar] : null,
        ];
    }
}
