<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Models\SewaKamar;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TagihanController extends Controller
{
    // GET /api/tagihan  (admin: semua | penghuni: milik sendiri)
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'owner') {
            $tagihans = Tagihan::with(['sewaKamar.pengguna', 'sewaKamar.kamar'])
                ->orderByDesc('bulan')
                ->get();
        } else {
            $sewa = SewaKamar::where('id_pengguna', $user->id_pengguna)->where('status_sewa', 'aktif')->first();
            if (! $sewa) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $tagihans = Tagihan::with(['sewaKamar.pengguna', 'sewaKamar.kamar'])
                ->where('id_sewa', $sewa->id_sewa)
                ->orderByDesc('bulan')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $tagihans->map(fn($t) => $this->formatTagihan($t)),
        ]);
    }

    // GET /api/tagihan/{id}
    public function show($id)
    {
        $tagihan = Tagihan::with(['sewaKamar.pengguna', 'sewaKamar.kamar', 'pembayaran'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->formatTagihan($tagihan, true)]);
    }

    // POST /api/tagihan  (admin: buat tagihan)
    public function store(Request $request)
    {
        $request->validate([
            'id_sewa' => 'required|integer|exists:sewa_kamar,id_sewa',
            'bulan'   => 'required|date',
            'jumlah'  => 'required|numeric|min:0',
            'denda'   => 'nullable|numeric|min:0',
        ]);

        // Cek duplikat
        $exists = Tagihan::where('id_sewa', $request->id_sewa)
            ->whereYear('bulan', Carbon::parse($request->bulan)->year)
            ->whereMonth('bulan', Carbon::parse($request->bulan)->month)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Tagihan bulan ini sudah ada.'], 422);
        }

        $tagihan = Tagihan::create([
            'id_sewa'         => $request->id_sewa,
            'bulan'           => $request->bulan,
            'jumlah'          => $request->jumlah,
            'denda'           => $request->denda ?? 0,
            'status_tagihan'  => 'Belum Dibayar',
        ]);

        // Kirim notifikasi ke penghuni
        $sewa = SewaKamar::find($request->id_sewa);
        if ($sewa) {
            Notifikasi::create([
                'id_pengguna'   => $sewa->id_pengguna,
                'id_tagihan'    => $tagihan->id_tagihan,
                'pesan'         => 'Tagihan bulan ' . Carbon::parse($request->bulan)->translatedFormat('F Y') . ' telah dibuat sebesar Rp ' . number_format($request->jumlah, 0, ',', '.'),
                'tanggal_kirim' => now(),
                'status_baca'   => 'Belum Dibaca',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Tagihan berhasil dibuat.', 'data' => $this->formatTagihan($tagihan->load('sewaKamar.pengguna'))], 201);
    }

    // PUT /api/tagihan/{id}/denda  (admin: tambah denda)
    public function updateDenda(Request $request, $id)
    {
        $tagihan = Tagihan::findOrFail($id);
        $request->validate(['denda' => 'required|numeric|min:0']);
        $tagihan->update(['denda' => $request->denda]);

        return response()->json(['success' => true, 'message' => 'Denda diperbarui.', 'data' => $this->formatTagihan($tagihan)]);
    }

    // POST /api/tagihan/generate-bulanan  (admin: generate tagihan otomatis semua penghuni aktif)
    public function generateBulanan(Request $request)
    {
        $request->validate(['bulan' => 'required|date']);

        $sewas = SewaKamar::with('kamar')->where('status_sewa', 'aktif')->get();
        $created = 0;
        $skipped = 0;

        foreach ($sewas as $sewa) {
            $exists = Tagihan::where('id_sewa', $sewa->id_sewa)
                ->whereYear('bulan', Carbon::parse($request->bulan)->year)
                ->whereMonth('bulan', Carbon::parse($request->bulan)->month)
                ->exists();

            if ($exists) { $skipped++; continue; }

            $tagihan = Tagihan::create([
                'id_sewa'        => $sewa->id_sewa,
                'bulan'          => $request->bulan,
                'jumlah'         => $sewa->kamar->harga,
                'denda'          => 0,
                'status_tagihan' => 'Belum Dibayar',
            ]);

            Notifikasi::create([
                'id_pengguna'   => $sewa->id_pengguna,
                'id_tagihan'    => $tagihan->id_tagihan,
                'pesan'         => 'Tagihan bulan ' . Carbon::parse($request->bulan)->translatedFormat('F Y') . ' sebesar Rp ' . number_format($sewa->kamar->harga, 0, ',', '.') . ' telah diterbitkan.',
                'tanggal_kirim' => now(),
                'status_baca'   => 'Belum Dibaca',
            ]);

            $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "Tagihan dibuat: $created, dilewati (sudah ada): $skipped",
        ]);
    }

    // GET /api/tagihan/summary  (admin: ringkasan)
    public function summary()
    {
        $belumDibayar = Tagihan::where('status_tagihan', 'Belum Dibayar')->count();
        $lunas        = Tagihan::where('status_tagihan', 'Lunas')->count();
        $totalPendapatan = Tagihan::where('status_tagihan', 'Lunas')->sum(\DB::raw('jumlah + denda'));

        return response()->json([
            'success' => true,
            'data'    => [
                'belum_dibayar'    => $belumDibayar,
                'lunas'            => $lunas,
                'total_pendapatan' => $totalPendapatan,
            ],
        ]);
    }

    private function formatTagihan(Tagihan $t, bool $detail = false): array
    {
        $data = [
            'id'              => $t->id_tagihan,
            'id_sewa'         => $t->id_sewa,
            'bulan'           => $t->bulan,
            'bulan_label'     => Carbon::parse($t->bulan)->translatedFormat('F Y'),
            'jumlah'          => $t->jumlah,
            'denda'           => $t->denda,
            'total'           => $t->jumlah + $t->denda,
            'total_format'    => 'Rp ' . number_format($t->jumlah + $t->denda, 0, ',', '.'),
            'status_tagihan'  => $t->status_tagihan,
            'penghuni'        => $t->sewaKamar?->pengguna ? ['id' => $t->sewaKamar->pengguna->id_pengguna, 'nama' => $t->sewaKamar->pengguna->nama] : null,
            'kamar'           => $t->sewaKamar?->kamar ? ['nomor' => $t->sewaKamar->kamar->nomor_kamar] : null,
        ];

        if ($detail && $t->relationLoaded('pembayaran')) {
            $data['pembayaran'] = $t->pembayaran->map(fn($p) => [
                'id'               => $p->id_pembayaran,
                'tanggal'          => $p->tanggal_pembayaran,
                'jumlah_bayar'     => $p->jumlah_bayar,
                'bukti'            => $p->bukti,
                'status_validasi'  => $p->status_validasi,
            ]);
        }

        return $data;
    }
}
