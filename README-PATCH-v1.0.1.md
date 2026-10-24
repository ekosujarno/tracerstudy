# PATCH TRACER STUDY KAMPUS v1.0.1 — LANDING PAGE SESUAI DESAIN

Patch ini hanya mengubah tampilan beranda publik serta navigasi/footer publik. Semua tabel MySQL, akun, data alumni, kuesioner, periode survei, laporan, pengaturan, aksi, keamanan, dan dashboard admin tetap digunakan.

## Instalasi pada situs yang SUDAH memakai v1.0.0

1. Cadangkan seluruh file website dan database MySQL terlebih dahulu.
2. cPanel > File Manager > masuk ke folder aplikasi, contoh `public_html/` pada subdomain tracerstudy.
3. Unggah `PATCH-TRACER-STUDY-KAMPUS-v1.0.1-BERANDA-CPANEL.zip` di folder yang sama dengan `index.php`.
4. Extract dan pilih **Overwrite** untuk file `app/core.php`, `app/layout.php`, `app/pages.php`, dan `assets/style.css`. File baru yang ditambahkan: `assets/logo-tracer.svg` dan `assets/hero-wisuda.webp`.
5. Refresh browser dengan Ctrl+F5. Tampilan beranda akan memakai visual baru.
6. **Tidak perlu** menjalankan install.php ulang; tidak ada SQL yang harus diimpor.

## Keterangan

- Gambar hero bawaan berasal dari rancangan desain yang sudah dibahas. Admin tetap dapat mengganti foto hero dan logo melalui halaman Pengaturan.
- Statistik (alumni, kuesioner, partisipasi, perusahaan penilai) tetap menggunakan data nyata dari database, bukan angka contoh pada gambar.
- Tombol Isi Tracer Study, Panduan, Login, Survei Pengguna, dan menu utama terhubung dengan route aplikasi yang sudah ada.
- Perubahan dibatasi pada beranda/navigasi publik. Tidak mengubah form login, aktivasi, dan panel internal.
- Untuk instalasi baru gunakan `INSTALLER-LENGKAP-TRACER-STUDY-KAMPUS-v1.0.1-FULL-CPANEL.zip`.
- Jangan overwrite folder `config/` saat memperbarui aplikasi yang sudah terpasang.
