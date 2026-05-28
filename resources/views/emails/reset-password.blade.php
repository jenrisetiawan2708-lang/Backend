<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
    .container { max-width: 520px; margin: 40px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .header { background: linear-gradient(to right, #2563eb, #60a5fa); padding: 32px; color: white; }
    .header h1 { margin: 0; font-size: 24px; }
    .body { padding: 32px; color: #374151; }
    .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 32px; border-radius: 99px; text-decoration: none; font-weight: bold; margin-top: 16px; }
    .footer { padding: 20px 32px; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🏠 HOMIA Kost</h1>
      <p style="margin: 4px 0 0; opacity: 0.85;">Reset Password</p>
    </div>
    <div class="body">
      <p>Halo, <strong>{{ $nama }}</strong>!</p>
      <p>Kami menerima permintaan untuk mereset password akun HOMIA Anda. Klik tombol di bawah untuk melanjutkan:</p>
      <a href="{{ config('app.url') }}/reset-password?token={{ $token }}&email={{ urlencode($email) }}" class="btn">Reset Password</a>
      <p style="margin-top: 24px; color: #6b7280; font-size: 14px;">Link ini akan kedaluwarsa dalam <strong>1 jam</strong>. Jika Anda tidak meminta reset password, abaikan email ini.</p>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} HOMIA Kost Management System. Semua hak dilindungi.
    </div>
  </div>
</body>
</html>
