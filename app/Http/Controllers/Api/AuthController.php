<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Google\Client as GoogleClient;

class AuthController extends Controller
{
    // ─── REGISTER ───────────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:100',
            'email'    => 'required|email|unique:pengguna,email',
            'username' => 'required|string|max:100|unique:pengguna,username',
            'password' => 'required|string|min:8|regex:/\d/',
        ]);

        $pengguna = Pengguna::create([
            'nama'     => $request->nama,
            'email'    => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role'     => 'penghuni',
        ]);

        $token = $pengguna->createToken('homia-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'user'    => $this->formatUser($pengguna),
        ], 201);
    }

    // ─── LOGIN PENGHUNI ─────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // cari by email ATAU username
        $pengguna = Pengguna::where('email', $request->email)
            ->orWhere('username', $request->email)
            ->first();

        if (! $pengguna || ! Hash::check($request->password, $pengguna->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/username atau password salah.',
            ], 401);
        }

        // Hapus token lama, buat token baru
        $pengguna->tokens()->delete();
        $token = $pengguna->createToken('homia-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => $this->formatUser($pengguna),
        ]);
    }

    // ─── LOGIN ADMIN ─────────────────────────────────────────────────
    public function loginAdmin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $pengguna = Pengguna::where('username', $request->username)
            ->where('role', 'owner')
            ->first();

        if (! $pengguna || ! Hash::check($request->password, $pengguna->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password admin salah.',
            ], 401);
        }

        $pengguna->tokens()->delete();
        $token = $pengguna->createToken('homia-admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login admin berhasil',
            'token'   => $token,
            'user'    => $this->formatUser($pengguna),
        ]);
    }

    // ─── GOOGLE LOGIN ────────────────────────────────────────────────
    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $payload = $client->verifyIdToken($request->id_token);

            if (! $payload) {
                return response()->json(['success' => false, 'message' => 'Token Google tidak valid.'], 401);
            }

            $googleEmail = $payload['email'];
            $googleName  = $payload['name'];

            // Cari atau buat pengguna
            $pengguna = Pengguna::firstOrCreate(
                ['email' => $googleEmail],
                [
                    'nama'     => $googleName,
                    'username' => Str::slug($googleName) . '_' . Str::random(4),
                    'password' => Hash::make(Str::random(32)),
                    'role'     => 'penghuni',
                ]
            );

            $pengguna->tokens()->delete();
            $token = $pengguna->createToken('homia-google-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login Google berhasil',
                'token'   => $token,
                'user'    => $this->formatUser($pengguna),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memverifikasi akun Google.'], 500);
        }
    }

    // ─── FORGOT PASSWORD ─────────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|string']);

        $pengguna = Pengguna::where('email', $request->email)
            ->orWhere('username', $request->email)
            ->first();

        if (! $pengguna) {
            // Kembalikan sukses untuk keamanan (tidak bocorkan info akun)
            return response()->json(['success' => true, 'message' => 'Instruksi reset telah dikirim jika akun terdaftar.']);
        }

        $resetToken = Str::random(64);
        Cache::put('reset_token_' . $pengguna->id_pengguna, $resetToken, now()->addHours(1));

        // Kirim email (pastikan MAIL_MAILER sudah dikonfigurasi)
        try {
            Mail::send('emails.reset-password', [
                'nama'  => $pengguna->nama,
                'token' => $resetToken,
                'email' => $pengguna->email,
            ], function ($msg) use ($pengguna) {
                $msg->to($pengguna->email)->subject('Reset Password HOMIA');
            });
        } catch (\Exception $e) {
            // Log error tapi tetap return sukses
            \Log::error('Failed to send reset email: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Instruksi reset telah dikirim ke email Anda.']);
    }

    // ─── RESET PASSWORD ──────────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|regex:/\d/',
            'password_confirmation' => 'required|same:password',
        ]);

        $pengguna = Pengguna::where('email', $request->email)->first();
        if (! $pengguna) {
            return response()->json(['success' => false, 'message' => 'Email tidak ditemukan.'], 404);
        }

        $cached = Cache::get('reset_token_' . $pengguna->id_pengguna);
        if (! $cached || $cached !== $request->token) {
            return response()->json(['success' => false, 'message' => 'Token reset tidak valid atau sudah kadaluarsa.'], 400);
        }

        $pengguna->update(['password' => Hash::make($request->password)]);
        Cache::forget('reset_token_' . $pengguna->id_pengguna);

        return response()->json(['success' => true, 'message' => 'Password berhasil direset.']);
    }

    // ─── LOGOUT ──────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    // ─── ME (get current user) ───────────────────────────────────────
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $this->formatUser($request->user()),
        ]);
    }

    // ─── HELPER ──────────────────────────────────────────────────────
    private function formatUser(Pengguna $p): array
    {
        return [
            'id'       => $p->id_pengguna,
            'nama'     => $p->nama,
            'email'    => $p->email,
            'username' => $p->username,
            'role'     => $p->role,
        ];
    }
}
