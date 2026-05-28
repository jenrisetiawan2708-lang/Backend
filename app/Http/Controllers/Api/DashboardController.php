<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kamar;
use App\Models\Pengguna;
use App\Models\SewaKamar;
use App\Models\Tagihan;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // GET /api/dashboard/admin
    public function admin()
    {
        $totalKamar   = Kamar::count();
        $kamarTerisi  = Kamar::where('status_kamar', 'terisi')->count();
        $kamarKosong  = Kamar::where('status_kamar', 'kosong')->count();
        $totalPenghuni = Pengguna::where('role', 'penghuni')->count();

        $bulanIni = Carbon::now()->startOfMonth();
        $tagihanBulanIni = Tagihan::whereYear('bulan', $bulanIni->year)->whereMonth('bulan', $bulanIni->month);
        $sudahBayar   = (clone $tagihanBulanIni)->where('status_tagihan', 'Lunas')->count();
        $belumBayar   = (clone $tagihanBulanIni)->where('status_tagihan', 'Belum Dibayar')->count();

        $pendapatanBulanIni = (clone $tagihanBulanIni)->where('status_tagihan', 'Lunas')
            ->join('pembayaran', 'tagihan.id_tagihan', '=', 'pembayaran.id_tagihan')
            ->where('pembayaran.status_validasi', 'Valid')
            ->sum('pembayaran.jumlah_bayar');

        $menungguValidasi = Pembayaran::where('status_validasi', 'Menunggu Validasi')->count();

        // Grafik pendapatan 6 bulan terakhir
        $grafik = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = Carbon::now()->subMonths($i);
            $pendapatan = Tagihan::whereYear('bulan', $bulan->year)
                ->whereMonth('bulan', $bulan->month)
                ->where('status_tagihan', 'Lunas')
                ->join('pembayaran', 'tagihan.id_tagihan', '=', 'pembayaran.id_tagihan')
                ->where('pembayaran.status_validasi', 'Valid')
                ->sum('pembayaran.jumlah_bayar');

            $grafik[] = [
                'bulan'      => $bulan->translatedFormat('M Y'),
                'pendapatan' => (float) $pendapatan,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kamar' => [
                    'total'  => $totalKamar,
                    'terisi' => $kamarTerisi,
                    'kosong' => $kamarKosong,
                ],
                'penghuni_total'      => $totalPenghuni,
                'tagihan_bulan_ini'   => ['sudah_bayar' => $sudahBayar, 'belum_bayar' => $belumBayar],
                'pendapatan_bulan_ini' => $pendapatanBulanIni,
                'menunggu_validasi'   => $menungguValidasi,
                'grafik_pendapatan'   => $grafik,
            ],
        ]);
    }

    // GET /api/dashboard/penghuni
    public function penghuni(Request $request)
    {
        $user = $request->user();
        $sewa = SewaKamar::with('kamar')->where('id_pengguna', $user->id_pengguna)->where('status_sewa', 'aktif')->first();

        if (! $sewa) {
            return response()->json([
                'success' => true,
                'data' => ['sewa' => null, 'tagihan_aktif' => null, 'unread_notif' => 0],
            ]);
        }

        $tagihanAktif = Tagihan::where('id_sewa', $sewa->id_sewa)
            ->where('status_tagihan', 'Belum Dibayar')
            ->orderBy('bulan')
            ->first();

        $unreadNotif = \App\Models\Notifikasi::where('id_pengguna', $user->id_pengguna)
            ->where('status_baca', 'Belum Dibaca')
            ->count();

        $jatuhTempo = $sewa->tanggal_masuk
            ? Carbon::parse($sewa->tanggal_masuk)->addMonths(1)->day(10)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'nama'        => $user->nama,
                'sewa' => [
                    'nomor_kamar'   => $sewa->kamar?->nomor_kamar,
                    'harga'         => $sewa->kamar?->harga,
                    'harga_format'  => 'Rp ' . number_format($sewa->kamar?->harga ?? 0, 0, ',', '.'),
                    'tanggal_masuk' => $sewa->tanggal_masuk,
                    'status_sewa'   => $sewa->status_sewa,
                ],
                'tagihan_aktif' => $tagihanAktif ? [
                    'id'           => $tagihanAktif->id_tagihan,
                    'bulan'        => Carbon::parse($tagihanAktif->bulan)->translatedFormat('F Y'),
                    'jumlah'       => $tagihanAktif->jumlah + $tagihanAktif->denda,
                    'jumlah_format' => 'Rp ' . number_format($tagihanAktif->jumlah + $tagihanAktif->denda, 0, ',', '.'),
                    'jatuh_tempo'  => $jatuhTempo?->toDateString(),
                ] : null,
                'unread_notif' => $unreadNotif,
            ],
        ]);
    }
}
