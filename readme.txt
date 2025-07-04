# Summary‑KAI
Aplikasi sederhana berbasis PHP untuk mencatat dan menganalisis laporan keuangan (pendapatan, beban, laba rugi) dengan antarmuka web yang mudah digunakan.

## 📁 Fitur Utama
- Autentikasi pengguna (login/logout)
- Manajemen laporan: tambah, edit, hapus pendapatan, beban, dan investasi
- Filter laporan berdasarkan kategori, bulan, tahun, dan pencarian kata kunci
- Ekspor laporan ke file (misalnya CSV/PDF)
- Sistem numbering otomatis untuk urutan laporan

## 🔧 Teknologi
- **Backend**: PHP 7+
- **Database**: MySQL (skrip `kai.sql`)
- **Frontend**: HTML, CSS, dan JavaScript dasar
- Struktur proyek:
  - `koneksi.php` – koneksi ke database
  - `login.php`, `proses_login.php`, `logout.php` – autentikasi pengguna
  - `dashboard.php` – halaman utama setelah login
  - File CRUD seperti `tambahpendapatan.php`, `edit_investasi.php`, dll. :contentReference[oaicite:1]{index=1}

## 🚀 Instalasi & Setup
1. **Clone** repo ini:<br>
   ```bash
   git clone https://github.com/syakillasalsa/summary-kai.git
   cd summary-kai
Import database:<br>
Jalankan file kai.sql di MySQL:

sql
Copy
Edit
CREATE DATABASE summary_kai;
USE summary_kai;
SOURCE path/to/kai.sql;
Konfigurasi koneksi database:<br>
Edit koneksi.php untuk menyesuaikan host, username, password, dan database.

Jalankan aplikasi:<br>
Tempatkan folder ke dalam directory webserver (misalnya htdocs/summary-kai) dan akses via browser:

pgsql
Copy
Edit
http://localhost/summary-main-master/login.php
🔐 Kredensial Login
Username: admin
Password: 123456
User ini diset sebagai akun admin default untuk mengakses seluruh fitur.

🧩 Penggunaan
Masuk sebagai admin menggunakan kredensial di atas
Tambahkan kategori laporan seperti pendapatan, beban, atau investasi
Gunakan fitur edit dan hapus sesuai kebutuhan
Filter dan cari data berdasarkan kategori, tanggal, dan kata kunci
Ekspor laporan jika diperlukan

🛠 Pengembangan Lanjutan
✨ Tambahkan validasi input dan sanitasi untuk keamanan
🔐 Implementasi manajemen user dan hak akses
📊 Tambah fitur grafik dan visualisasi laporan
🧩 Integrasi export ke format PDF

📝 Lisensi
MIT License – bebas digunakan dan dimodifikasi
