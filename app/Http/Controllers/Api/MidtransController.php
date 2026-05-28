<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Models\Pembayaran;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

/**
 * Payment Gateway menggunakan Midtrans Snap API.
 *
 * Konfigurasi di .env:
 *   MIDTRANS_SERVER_KEY=SB-Mid-server-XXXX   (Sandbox)
 *   MIDTRANS_CLIENT_KEY=SB-Mid-client-XXXX
 *   MIDTRANS_IS_PRODUCTION=false
 */
class MidtransController extends Controller
{
    private function getServerKey(): string
    {
        return config('services.midtrans.server_key');
    }

    private function isProduction(): bool
    {
        return config('services.midtrans.is_production', false);
    }

    private function snapUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    // POST /api/midtrans/create-transaction
    // Frontend memanggil ini → backend buat Snap token → frontend pakai Snap popup
    public function createTransaction(Request $request)
    {
        $request->validate(['id_tagihan' => 'required|integer|exists:tagihan,id_tagihan']);

        $tagihan = Tagihan::with(['sewaKamar.pengguna', 'sewaKamar.kamar'])->findOrFail($request->id_tagihan);

        if ($tagihan->status_tagihan === 'Lunas') {
            return response()->json(['success' => false, 'message' => 'Tagihan sudah lunas.'], 422);
        }

        $pengguna    = $tagihan->sewaKamar->pengguna;
        $orderId     = 'HOMIA-' . $tagihan->id_tagihan . '-' . time();
        $grossAmount = (int) ($tagihan->jumlah + $tagihan->denda);

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $pengguna->nama,
                'email'      => $pengguna->email,
            ],
            'item_details' => [
                [
                    'id'       => 'TAGIHAN-' . $tagihan->id_tagihan,
                    'price'    => $grossAmount,
                    'quantity' => 1,
                    'name'     => 'Tagihan Kost ' . $tagihan->bulan . ' - Kamar ' . ($tagihan->sewaKamar->kamar->nomor_kamar ?? ''),
                ],
            ],
            'enabled_payments' => [
                'credit_card', 'bca_va', 'bni_va', 'bri_va', 'mandiri_clickpay',
                'cimb_clicks', 'danamon_online', 'permata_va', 'other_va',
                'gopay', 'shopeepay', 'indomaret', 'alfamart',
            ],
        ];

        $response = $this->callMidtrans($params);

        if (! $response || ! isset($response['token'])) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat transaksi Midtrans.'], 500);
        }

        // Simpan order_id di tagihan (tambahkan kolom midtrans_order_id di migration jika perlu)
        $tagihan->update(['midtrans_order_id' => $orderId]);

        return response()->json([
            'success'         => true,
            'snap_token'      => $response['token'],
            'redirect_url'    => $response['redirect_url'],
            'order_id'        => $orderId,
            'midtrans_client_key' => config('services.midtrans.client_key'),
            'is_production'       => $this->isProduction(),
        ]);
    }

    // POST /api/midtrans/notification  (Midtrans webhook)
    public function handleNotification(Request $request)
    {
        $payload    = $request->all();
        $orderId    = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';

        // Validasi signature key
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $this->getServerKey());
        if ($signatureKey !== ($payload['signature_key'] ?? '')) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status'] ?? 'accept';

        // Cari tagihan berdasarkan order_id
        // Order ID format: HOMIA-{id_tagihan}-{timestamp}
        preg_match('/HOMIA-(\d+)-/', $orderId, $matches);
        $idTagihan = $matches[1] ?? null;

        if (! $idTagihan) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $tagihan = Tagihan::with('sewaKamar')->find($idTagihan);
        if (! $tagihan) {
            return response()->json(['message' => 'Tagihan not found'], 404);
        }

        if (
            ($transactionStatus === 'capture' && $fraudStatus === 'accept') ||
            $transactionStatus === 'settlement'
        ) {
            // Pembayaran berhasil
            $tagihan->update(['status_tagihan' => 'Lunas']);

            // Buat record pembayaran
            $pembayaran = Pembayaran::create([
                'id_tagihan'         => $tagihan->id_tagihan,
                'tanggal_pembayaran' => now()->toDateString(),
                'jumlah_bayar'       => $payload['gross_amount'],
                'bukti'              => 'midtrans:' . ($payload['transaction_id'] ?? $orderId),
                'status_validasi'    => 'Valid',
            ]);

            // Notifikasi ke penghuni
            if ($tagihan->sewaKamar) {
                Notifikasi::create([
                    'id_pengguna'   => $tagihan->sewaKamar->id_pengguna,
                    'id_tagihan'    => $tagihan->id_tagihan,
                    'pesan'         => 'Pembayaran tagihan bulan ' . $tagihan->bulan . ' berhasil via Midtrans. Status: LUNAS.',
                    'tanggal_kirim' => now(),
                    'status_baca'   => 'Belum Dibaca',
                ]);
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            // Pembayaran gagal/expired - tidak ubah status tagihan, biarkan tetap Belum Dibayar
            \Log::info("Midtrans payment {$transactionStatus} for order {$orderId}");
        }

        return response()->json(['message' => 'OK']);
    }

    // GET /api/midtrans/status/{orderId}  (cek status transaksi)
    public function checkStatus($orderId)
    {
        $url = ($this->isProduction()
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com') . "/v2/{$orderId}/status";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($this->getServerKey() . ':'),
                'Content-Type: application/json',
            ],
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return response()->json(['success' => true, 'data' => $response]);
    }

    private function callMidtrans(array $params): ?array
    {
        $ch = curl_init($this->snapUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($this->getServerKey() . ':'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \Log::error('Midtrans cURL error: ' . $error);
            return null;
        }

        return json_decode($response, true);
    }
}
