# HOMIA Backend — Laravel REST API

## Teknologi
- **Laravel 13** + **Sanctum** (token-based auth)
- **MySQL** database
- **Midtrans** payment gateway
- **Google OAuth** (via Google Identity Services)

---

## ⚡ Quick Setup

```bash
cd backend

# 1. Install dependencies
composer install

# 2. Buat .env
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database di .env
# DB_DATABASE=sistem_kos
# DB_USERNAME=root
# DB_PASSWORD=

# 4. Jalankan migrasi + seeder
php artisan migrate --seed

# 5. Buat symlink storage (untuk upload bukti pembayaran)
php artisan storage:link

# 6. Jalankan server
php artisan serve
# → http://localhost:8000
```

---

## 🔑 Konfigurasi .env (Wajib)

### Google OAuth
1. Buka https://console.cloud.google.com/
2. Buat project baru atau pilih yang ada
3. APIs & Services → Credentials → Create OAuth 2.0 Client ID
4. Application type: **Web application**
5. Authorized JavaScript origins: `http://localhost:5173`
6. Authorized redirect URIs: `http://localhost:8000/auth/google/callback`
7. Copy Client ID dan Client Secret ke `.env`:
```
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-secret
```

### Midtrans Payment Gateway
1. Daftar di https://dashboard.sandbox.midtrans.com/
2. Settings → Access Keys
3. Copy Server Key & Client Key ke `.env`:
```
MIDTRANS_SERVER_KEY=SB-Mid-server-XXXX
MIDTRANS_CLIENT_KEY=SB-Mid-client-XXXX
MIDTRANS_IS_PRODUCTION=false
```
4. Settings → Configuration → tambahkan Payment Notification URL:
   `http://your-domain.com/api/midtrans/notification`

### Mail (Reset Password)
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your_app_password   ← App Password, bukan password biasa
MAIL_ENCRYPTION=tls
```

---

## 📡 API Endpoints

### Auth (Public)
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/auth/register` | Daftar penghuni baru |
| POST | `/api/auth/login` | Login penghuni |
| POST | `/api/auth/login-admin` | Login admin/owner |
| POST | `/api/auth/google` | Login dengan Google (kirim `id_token`) |
| POST | `/api/auth/forgot-password` | Kirim link reset password |
| POST | `/api/auth/reset-password` | Reset password |
| POST | `/api/midtrans/notification` | Webhook Midtrans |

### Auth (Perlu Token)
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/auth/logout` | Logout |
| GET | `/api/auth/me` | Data user aktif |

### Dashboard
| Method | Endpoint | Akses |
|--------|----------|-------|
| GET | `/api/dashboard/penghuni` | Penghuni |
| GET | `/api/dashboard/admin` | Admin saja |

### Kamar
| Method | Endpoint | Akses |
|--------|----------|-------|
| GET | `/api/kamar` | Semua |
| GET | `/api/kamar/{id}` | Semua |
| GET | `/api/kamar/summary` | Admin |
| POST | `/api/kamar` | Admin |
| PUT | `/api/kamar/{id}` | Admin |
| DELETE | `/api/kamar/{id}` | Admin |

### Penghuni
| GET | `/api/penghuni` | Admin |
| GET | `/api/penghuni/me` | Penghuni sendiri |
| POST | `/api/penghuni` | Admin |
| PUT | `/api/penghuni/{id}` | Admin/Penghuni |
| DELETE | `/api/penghuni/{id}` | Admin |

### Tagihan
| GET | `/api/tagihan` | Semua (filter by role) |
| GET | `/api/tagihan/{id}` | Semua |
| POST | `/api/tagihan` | Admin |
| PUT | `/api/tagihan/{id}/denda` | Admin |
| POST | `/api/tagihan/generate-bulanan` | Admin |

### Pembayaran
| POST | `/api/pembayaran` | Penghuni (upload bukti) |
| GET | `/api/pembayaran/menunggu` | Admin |
| PUT | `/api/pembayaran/{id}/validasi` | Admin |

### Midtrans (Payment Gateway)
| POST | `/api/midtrans/create-transaction` | Penghuni (buat Snap token) |
| GET | `/api/midtrans/status/{orderId}` | Semua |

### Forum
| GET | `/api/forum` | Semua |
| POST | `/api/forum` | Semua |
| DELETE | `/api/forum/{id}` | Pemilik/Admin |

### Notifikasi
| GET | `/api/notifikasi` | Milik sendiri |
| PUT | `/api/notifikasi/{id}/baca` | Milik sendiri |
| PUT | `/api/notifikasi/baca-semua` | Milik sendiri |

### Pengumuman
| GET | `/api/pengumuman` | Semua |
| POST | `/api/pengumuman` | Admin (broadcast) |

---

## 🔐 Autentikasi

Semua endpoint protected memerlukan header:
```
Authorization: Bearer {token}
```

Token didapat setelah login/register.

---

## 👤 Akun Default (Seeder)

| Role | Username | Password |
|------|----------|----------|
| Admin/Owner | `admin` | `admin123` |
| Penghuni | `raja` | `password123` |
| Penghuni | `budi` | `password123` |
