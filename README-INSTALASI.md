# TRACER STUDY KAMPUS v1.0.0 — PHP Native + MySQL

Paket aplikasi siap diunggah ke cPanel, dilengkapi installer database dan tampilan responsif hijau–emas.

## Spesifikasi minimum

- PHP **8.1+** (direkomendasikan 8.2/8.3/8.4).
- MySQL **5.7+** atau MariaDB **10.3+**, ekstensi `pdo_mysql`, `openssl` bawaan PHP.
- Untuk file Excel XLSX: ekstensi `zip` dan `simplexml` aktif. Untuk unggahan gambar, `fileinfo` dan fungsi `getimagesize()` diperlukan.
- Web server Apache/cPanel dengan dukungan `.htaccess` untuk memblokir direktori internal.
- HTTPS diaktifkan sebelum digunakan oleh alumni.

## Instalasi cPanel

1. Buka **cPanel → MySQL Databases**. Buat database dan pengguna MySQL, kemudian tautkan user dengan hak akses database.
2. Buka **File Manager**. Unggah isi ZIP ke folder web yang dituju, misalnya `public_html/tracer/` (atau `public_html/` jika di root domain). Ekstrak semua file.
3. Pastikan folder `config/` dapat ditulis oleh PHP selama instalasi. Setelah selesai, batasi kembali izin folder.
4. Buka `https://domain-anda/tracer/install.php`. Isi koneksi database, nama kampus, email admin, dan password admin minimal 12 karakter.
5. Klik **Instal Aplikasi** lalu masuk melalui `index.php?page=login`.
6. **Hapus `install.php` dari hosting setelah instalasi**. Jangan membagikan `config/config.php` atau file berisi kode aktivasi.
7. Atur Program Studi → Impor Alumni → Buat Periode → Aktifkan Periode → Tambahkan Pertanyaan (opsional).
8. Impor CSV/XLSX menghasilkan unduhan CSV kode aktivasi hanya bagi alumni baru. Simpan aman dan kirim kode **secara privat** ke masing-masing alumni.
9. Alumni membuka beranda, memilih **Aktivasi**, lalu masuk dan mengisi survei. Untuk survei pengguna lulusan gunakan menu publik **Survei Pengguna**.

## Hak akses

| Peran | Fitur |
|---|---|
| Admin | Semua pengelolaan, admin pengguna, periode, pengaturan, log |
| Operator | Impor, kelola alumni, kuesioner, rekap **khusus prodi operator** |
| Pimpinan | Dashboard dan laporan (read-only) |
| Alumni | Biodata sendiri, draft dan submit survei sendiri |
| Pengguna lulusan | Form survei publik, menunggu verifikasi operator |

## Template impor

Buka **Admin → Impor Alumni → Unduh Template CSV**. Baris header (harus sama):

`nim,nama,email,telepon,kode_prodi,tahun_lulus`

Contoh:

`20200001,Nama Alumni,alumni@example.com,081234567890,PAI,2024`

Kode Prodi wajib ada di menu Program Studi. Maksimal 5 MB dan 10.000 baris per file. Data NIM yang sudah ada diperbarui; tidak membuat ulang kode aktivasi pengguna aktif.

## Analitik dan laporan

- Dashboard respons per periode dan prodi; status pekerjaan, masa tunggu, relevansi bidang kerja, dan kepuasan pengguna lulusan.
- Admin dapat mengunggah logo kampus dan gambar hero halaman depan dari Pengaturan Sistem.
- Menu Pengingat Alumni menyediakan WhatsApp tautan manual dan ekspor kontak untuk target yang belum mengisi.
- Laporan ringkasan PDF dan XLSX native tanpa Composer, ditambah tampilan cetak browser.
- PDF merupakan ringkasan tabel A4 horizontal, tidak mencakup visual grafik interaktif.
- Penghitungan partisipasi merupakan target alumni **berdasarkan tahun kelulusan dan prodi**, dibandingkan respons berstatus final pada periode tersebut.

## Keamanan dan batasan

- PDO prepared statements; hash password PHP; CSRF untuk seluruh POST; cookie sesi HttpOnly dan SameSite.
- File konfigurasi dan log berada pada direktori yang diblokir oleh `.htaccess`. **Jangan jalankan instalasi pada server yang mengabaikan `.htaccess` tanpa proteksi setara!**
- Batasi percobaan login dan lakukan perlindungan WAF/rate limit hosting jika tersedia. Rate limit bawaan adalah berbasis sesi.
- Kode alumni berbentuk token acak 10 karakter heksadesimal yang disimpan sebagai SHA-256; ditampilkan hanya ketika ditambahkan atau diganti.
- Sistem **tidak mengirim WhatsApp/email otomatis**. Distribusi kode akses dikerjakan pengelola.
- Modul update hanya menampilkan versi dan panduan pembaruan manual melalui cPanel, **bukan** upload ZIP executable.
- Sinkronisasi otomatis SIKADU, PDDIKTI/NeoFeeder, atau platform Tracer Study nasional **belum terpasang**; gunakan impor lokal CSV/XLSX dan verifikasi kesesuaian instrumen resmi sebelum pelaporan.
- Sebelum produksi, diperlukan pengujian koneksi MySQL, UAT workflow, audit keamanan, backup/restore, dan penyesuaian kebijakan privasi/retensi kampus.
- Setelah mengubah tahun target suatu periode, pastikan tidak ada laporan yang salah interpretasi; periode dapat memiliki respons dari pengisian sebelumnya.

## Struktur utama

- `install.php`: installer dan pembuatan akun admin pertama.
- `index.php`: router aplikasi.
- `app/core.php`: koneksi, autentikasi, keamanan, utilitas.
- `app/schema.php`: skema database.
- `app/actions.php`: validasi dan penyimpanan data.
- `app/pages.php`: halaman portal/admin/alumni.
- `app/import.php`, `app/exports.php`: impor CSV/XLSX dan ekspor CSV/XLSX/PDF.
- `assets/style.css`, `assets/app.js`: UI modern dan interaksi dasar.

**Versi:** 1.0.0 — edisi awal instalasi mandiri.
