
## 📁 Fitur Utama

* ✅ Autentikasi pengguna (login/logout)
* 🧾 Manajemen laporan: tambah, edit, hapus pendapatan, beban, dan investasi
* 🔎 Filter laporan berdasarkan kategori, bulan, tahun, dan kata kunci
* 📤 Ekspor laporan ke file (CSV/PDF)
* 🔢 Sistem penomoran otomatis untuk laporan

---

## 🔧 Teknologi

* **Backend**: PHP 7+
* **Database**: MySQL (`kai.sql`)
* **Frontend**: HTML, CSS, dan JavaScript dasar

📂 **Struktur Proyek:**

* `koneksi.php` – koneksi ke database
* `login.php`, `proses_login.php`, `logout.php` – autentikasi pengguna
* `dashboard.php` – halaman utama setelah login
* File CRUD: `tambahpendapatan.php`, `edit_investasi.php`, dll.

---

## 🚀 Instalasi & Setup

1. **Clone repository**

   ```bash
   git clone https://github.com/syakillasalsa/summary-kai.git
   cd summary-kai
   ```

2. **Import database**

   * Buka phpMyAdmin
   * Buat database baru: `summary_kai`
   * Import file `kai.sql`

3. **Konfigurasi koneksi database**

   * Buka file `koneksi.php`
   * Sesuaikan `host`, `username`, `password`, dan `database`

4. **Jalankan aplikasi**

   * Tempatkan folder ini di `htdocs/` jika menggunakan XAMPP
   * Akses di browser:
     `http://localhost/summary-kai/login.php`

---

## 🔐 Kredensial Login

| Role  | Username | Password |
| ----- | -------- | -------- |
| Admin | admin    | 123456   |

---

## 🧩 Cara Penggunaan

1. Login menggunakan akun admin
2. Tambahkan data: pendapatan, beban, atau investasi
3. Edit atau hapus data sesuai kebutuhan
4. Gunakan fitur filter & pencarian
5. Ekspor laporan jika dibutuhkan

---

## 🛠 Pengembangan Lanjutan

* ✨ Validasi input & sanitasi untuk keamanan
* 🔐 Manajemen user & hak akses
* 📊 Tambah grafik visualisasi laporan
* 🧾 Ekspor laporan ke format PDF

---

## 📜 Lisensi

MIT License – bebas digunakan dan dimodifikasi

