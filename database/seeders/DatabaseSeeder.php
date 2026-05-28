<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Pengguna;
use App\Models\Kamar;
use App\Models\Fasilitas;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Fasilitas
        $fasilitasData = ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV', 'Kulkas', 'Laundry', 'Dapur Umum', 'Gym', 'Cleaning Service'];
        $fasilitasIds = [];
        foreach ($fasilitasData as $nama) {
            $f = Fasilitas::firstOrCreate(['nama_fasilitas' => $nama]);
            $fasilitasIds[$nama] = $f->id_fasilitas;
        }

        // ── Admin / Owner
        Pengguna::firstOrCreate(['email' => 'admin@homia.id'], [
            'nama'     => 'Admin HOMIA',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role'     => 'owner',
        ]);

        // ── Kamar sample
        $kamars = [
            ['P01', 1200000, 'kosong', ['WiFi', 'AC', 'Kamar Mandi Dalam']],
            ['P02', 1200000, 'kosong', ['WiFi', 'AC', 'Kamar Mandi Dalam']],
            ['P03', 1500000, 'kosong', ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV']],
            ['P04', 1500000, 'kosong', ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV']],
            ['P05', 1800000, 'kosong', ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV', 'Kulkas']],
            ['P06', 1500000, 'terisi', ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV']],
            ['P07', 1500000, 'terisi', ['WiFi', 'AC', 'Kamar Mandi Dalam', 'Smart TV']],
            ['P08', 1200000, 'terisi', ['WiFi', 'AC', 'Kamar Mandi Dalam']],
        ];

        foreach ($kamars as [$nomor, $harga, $status, $fasilitas]) {
            $kamar = Kamar::firstOrCreate(['nomor_kamar' => $nomor], [
                'harga'        => $harga,
                'status_kamar' => $status,
            ]);
            $ids = array_map(fn($n) => $fasilitasIds[$n], $fasilitas);
            $kamar->fasilitas()->syncWithoutDetaching($ids);
        }

        // ── Penghuni sample
        $penghuniData = [
            ['Andi Saputra', 'andi@gmail.com', 'andi', 'P01'],
            ['Budi Santoso', 'budi@gmail.com', 'budi', 'P06'],
            ['Raja Esa Abdilah', 'raja@gmail.com', 'raja', 'P07'],
        ];

        foreach ($penghuniData as [$nama, $email, $username, $noKamar]) {
            $p = Pengguna::firstOrCreate(['email' => $email], [
                'nama'     => $nama,
                'username' => $username,
                'password' => Hash::make('password123'),
                'role'     => 'penghuni',
            ]);

            $kamar = Kamar::where('nomor_kamar', $noKamar)->first();
            if ($kamar && ! \App\Models\SewaKamar::where('id_pengguna', $p->id_pengguna)->where('status_sewa', 'aktif')->exists()) {
                \App\Models\SewaKamar::create([
                    'id_pengguna'   => $p->id_pengguna,
                    'id_kamar'      => $kamar->id_kamar,
                    'tanggal_masuk' => now()->subMonths(3)->toDateString(),
                    'status_sewa'   => 'aktif',
                ]);
                $kamar->update(['status_kamar' => 'terisi']);
            }
        }
    }
}
