# GameIndo — Panduan Instalasi WordPress di Hostinger

Panduan ini memasang GameIndo sebagai **tema WordPress native** di Hostinger.
Setelah selesai, **seluruh situs dikelola dari wp-admin**: artikel, halaman,
menu, kategori (pilar), penulis, dan widget esports (ticker, topik hangat,
match center, klasemen).

Paket yang Anda terima (folder `cms/dist/`):

| Berkas | Isi |
|---|---|
| `gameindo-theme.zip` | Tema tampilan situs |
| `gameindo-core-plugin.zip` | Plugin model konten (meta artikel, profil penulis, widget esports) |
| `gameindo-content.xml` | Konten demo (opsional) — 23 artikel, 5 penulis, menu, widget |

> **Urutan penting:** pasang **plugin dulu**, baru **tema**. Plugin yang
> membuat kategori pilar & menyediakan panel meta yang dipakai tema.

---

## 1. Pasang WordPress di Hostinger

Di **hPanel → Website → Auto Installer → WordPress**. Ikuti wizard
(pilih domain, buat akun admin). Tunggu hingga selesai, lalu masuk ke
`https://domainanda.com/wp-admin`.

Jika WordPress sudah terpasang, lanjut ke langkah 2.

## 2. Pasang & aktifkan plugin GameIndo Core

1. wp-admin → **Plugin → Tambah Plugin Baru → Unggah Plugin**.
2. Pilih `gameindo-core-plugin.zip` → **Pasang Sekarang** → **Aktifkan**.

Saat aktif, plugin otomatis membuat 5 kategori pilar dengan slug tetap:
`home` (Video Game), `esports`, `streamer`, `tech`, `entertainment`.
**Nama boleh diubah; slug jangan diubah** — slug inilah yang mengendalikan
warna tiap pilar.

## 3. Pasang & aktifkan tema GameIndo

1. wp-admin → **Tampilan → Tema → Tambah Tema Baru → Unggah Tema**.
2. Pilih `gameindo-theme.zip` → **Pasang Sekarang** → **Aktifkan**.

## 4. Atur permalink (wajib)

**Pengaturan → Permalink → pilih "Nama tulisan" (Post name)** → Simpan.
Ini membuat URL bersih seperti `/judul-artikel/` dan `/category/esports/`.

## 5. (Opsional) Impor konten demo

Ada dua cara. Pilih salah satu.

### Cara A — Impor cepat lewat wp-admin (tanpa gambar contoh)
1. **Peralatan → Impor → WordPress → Jalankan Importer**
   (pasang plugin "WordPress Importer" bila diminta).
2. Unggah `gameindo-content.xml`.
3. Saat diminta memetakan penulis, biarkan default (penulis akan dibuat).
4. **Jangan** centang "unduh & impor lampiran berkas" (URL gambar demo
   menunjuk ke server lokal dan tidak akan berhasil).
5. Setelah impor: **Tampilan → Menu → Kelola Lokasi**, dan tetapkan
   menu **Pilar Utama → Header**, **Footer → Footer**, **Menu Mobile → Drawer**.

Gambar unggulan artikel demo akan kosong dan otomatis memakai placeholder
berwarna pilar — normal. Ganti dengan gambar asli kapan saja.

### Cara B — Impor penuh + gambar (via SSH/wp-cli, disarankan untuk demo utuh)
Hostinger menyediakan wp-cli lewat SSH. Unggah folder `import/`, `data/`,
dan `assets/` ke server, lalu jalankan dari root WordPress:

```bash
wp eval-file import/import.php
```

Skrip ini mengimpor artikel + gambar + penulis + widget + menu + halaman
sekaligus, dan bersifat aman diulang (idempoten).

> **Situs baru tanpa demo?** Lewati langkah 5. Situs siap diisi konten Anda
> sendiri — menu & nav pilar otomatis terbentuk dari kategori.

## 6. Setelan tambahan (disarankan)

- **Pengaturan → Umum:** Zona waktu `Jakarta`, Format tanggal `j M Y`.
- **Pengaturan → Umum → Situs Bahasa:** `Bahasa Indonesia`.
- **Tampilan → Sesuaikan → Logo:** unggah logo bila ingin mengganti default.

---

## Cara mengelola situs sehari-hari

### Menulis artikel
**Pos → Tambah Pos Baru.** Isi judul & konten seperti biasa, lalu:
- **Kategori:** pilih salah satu pilar (menentukan warna & posisi di situs).
- Panel **"GameIndo — Meta Artikel"** (kolom kanan editor):
  - **Subkategori** — label pill di kartu (mis. `MPL ID`, `Handheld`).
  - **Waktu baca** — kosongkan untuk hitung otomatis.
  - **Jumlah dibaca** — angka popularitas (mis. `128 rb`) untuk rail Terpopuler.
    Boleh dikosongkan: kalau kosong, rail Terpopuler jatuh ke urutan artikel
    terbaru (lihat "Cara kerja rail Terpopuler" di bawah).
  - **Featured** — jadikan hero utama beranda (pilih satu artikel saja).
  - **Spotlight** — tampil di rail trending beranda.
- **Tag:** dipakai sebagai hashtag di bawah artikel & di tag cloud pencarian.
- **Gambar Unggulan:** jadi gambar hero & thumbnail kartu.

### Widget esports (menu kiri wp-admin)
- **PandaScore** — **sumber utama jadwal match.** Isi token API di sini, lalu
  jadwal enam game terisi otomatis. Lihat bagian di bawah.
- **Live Ticker** — teks berjalan di header sekarang **terisi otomatis dari 12
  artikel terbaru** Anda, jadi menu ini **tidak lagi memengaruhi tampilan situs**
  dan tidak perlu diisi. Item demo bawaan boleh dihapus atau dibiarkan — sama
  saja, tidak akan muncul.
  Kecepatan jalannya dipatok **45 piksel/detik** dan dihitung dari panjang isi,
  jadi tetap enak dibaca berapa pun jumlah dan panjang judulnya. Ticker
  **berhenti saat kursor diarahkan ke sana** (atau ditekan-tahan di HP) supaya
  judulnya bisa diklik. Mau lebih pelan/cepat? Tambahkan di `functions.php`
  (angka lebih kecil = lebih pelan):

  ```php
  add_filter( 'gameindo_ticker_speed', function () { return 35; } );
  ```
- **Topik Hangat** — chip di bawah header beranda. **Sekarang terisi otomatis**,
  jadi menu ini tidak perlu diisi kecuali Anda memang ingin menyematkan sesuatu.
  Isinya dirakit dari: tim yang sedang bertanding, kompetisi yang live atau mulai
  dalam 5 hari ke depan, lalu tag artikel. Yang sedang live dikunci di depan
  (dengan titik merah); sisanya **berputar tiap 10 menit** supaya baris ini tidak
  pernah terlihat sama saat pengunjung kembali.

  Kalau Anda mengisi menu ini, topik itu tampil paling depan — **maksimal 3**,
  supaya selalu tersisa ruang untuk yang sedang hangat. Kosongkan (ubah ke Draf
  atau hapus) dan baris kembali 100% otomatis. Enam topik demo bawaan sudah
  di-Draf pada 17 Agu 2026 karena sudah tidak relevan.

  Tag yang berupa format (`Review`, `Guide`, `Tips`) dan tag yang cuma dipakai
  1 artikel sengaja tidak ikut — itu jenis tulisan atau jalan buntu, bukan topik.
- **Match Center** — jadwal/skor manual. Sekarang hanya dipakai **sebagai
  cadangan**: isinya baru tampil kalau token PandaScore kosong atau API-nya
  sedang bermasalah.
- **Klasemen** — **tidak lagi ditampilkan di situs.** Panel klasemen di halaman
  Esports sudah diganti panel Jadwal. Data lama tetap aman tersimpan di sini
  kalau suatu saat mau dipakai lagi.

Semua bisa di-*drag* untuk mengubah urutan (kolom "Urutan").

### Jadwal match otomatis (PandaScore)

Jadwal di **beranda** dan **halaman Esports** diambil dari
[PandaScore](https://pandascore.co) untuk enam game: **ML:BB, CS:GO, Valorant,
LoL, DotA 2, Overwatch**.

**Pasang token:**

1. Daftar di PandaScore, salin token dari dasbor mereka.
2. Cara paling aman — tambahkan di `wp-config.php`, **di atas** baris
   `/* That's all, stop editing! */`:

   ```php
   define( 'GAMEINDO_PANDASCORE_TOKEN', 'token-anda-di-sini' );
   ```

   Token tidak masuk database dan tidak ikut ter-backup ke tempat lain.
3. Alternatifnya: **GameIndo → PandaScore** di wp-admin, tempel di kolom
   *Token API*, Simpan. (Kalau konstanta di `wp-config.php` ada, konstanta itu
   yang dipakai dan kolom ini diabaikan.)
4. Klik **Tes koneksi** untuk memastikan token valid, lalu **Bersihkan cache &
   ambil ulang** supaya jadwal langsung terisi.

**Yang perlu diketahui:**

- Semua pengambilan data dilakukan **di server** — token tidak pernah terkirim
  ke browser pengunjung.
- Hasilnya di-*cache*: skor live disegarkan tiap **3 menit**, jadwal tiap
  **15 menit**, dan sebuah cron tiap 5 menit menjaga cache tetap hangat.
  Pengunjung tidak pernah menunggu PandaScore — halaman selalu tampil instan.
- Kuota gratis PandaScore 1.000 request/jam; pemakaian pola ini sekitar
  **±100 request/jam**, jadi jauh di bawah batas.
- Kalau API mati, situs **tetap menampilkan jadwal terakhir** yang tersimpan
  (sampai 24 jam), lalu jatuh ke data manual **Match Center**. Panel tidak
  pernah kosong mendadak.
- Baris jadwal yang punya siaran resmi bisa **diklik langsung ke streamnya**
  (YouTube/Twitch/Kick). Siaran resmi berbahasa Indonesia diprioritaskan —
  MPL Indonesia menandai stream YouTube resminya, jadi tombolnya tampil
  sebagai **▶ YouTube ID**.
- Beranda menampilkan **4 baris turnamen bergengsi**, dengan urutan:
  1. **Gengsi turnamen lebih dulu** — status live saja tidak cukup. Kualifikasi
     kecil (NODWIN, Exort Fiesta, kualifikasi tertutup CCT) tidak boleh memakan
     slot beranda hanya karena kebetulan sedang live.
  2. Di antara yang bergengsi: yang **sedang live** dulu, lalu **hari terdekat**,
     lalu **ML:BB** diprioritaskan dalam hari yang sama.
  3. **Maksimal 2 baris per game**, supaya beranda tidak habis dipakai satu liga
     yang malam itu kebetulan ramai.

  Batas gengsinya = **tier C ke atas**. Ini menjaga Overwatch World Cup, KeSPA
  Cup, dan ESL Challenger League tetap tampil bersama MPL, LEC, VCT, dan The
  International — sekaligus menyingkirkan kualifikasi. Perlu dicatat: match tier
  rendah **tidak dibuang, hanya diturunkan**. Kalau suatu hari tidak ada event
  besar sama sekali, beranda tetap terisi dan tidak pernah kosong.

  Mau lebih ketat (hanya turnamen major) atau lebih longgar? Ubah lewat filter
  di `functions.php`:

  ```php
  add_filter( 'gameindo_prestige_tier_floor', function () { return 'b'; } );
  ```
- Chip di halaman Esports (**Semua / ML:BB / CS:GO / Valorant / LoL / DotA 2 /
  Overwatch**) menyaring **panel Jadwal saja**; daftar artikel di bawahnya tetap
  seluruh artikel pilar Esports. Filter tersimpan di URL (`?game=csgo`) jadi bisa
  dibagikan.

### Cara kerja rail "Terpopuler"
Aturannya sederhana dan bisa diprediksi:

1. Ambil artikel yang terbit dalam **7 hari terakhir**.
2. Urutkan dari **"Jumlah dibaca" terbesar**.
3. Kalau angkanya **seri atau kosong**, yang **terbit paling baru** menang.

Efeknya di lapangan: selama kolom "Jumlah dibaca" belum Anda isi, rail ini
otomatis menampilkan artikel terbaru Anda — artikel < 48 jam ditandai label
merah **BARU** dengan keterangan "2 jam lalu". Begitu Anda mulai mengisi angka
dibaca, artikel yang benar-benar ramai minggu itu naik ke puncak.

Kalau dalam 7 hari terakhir belum ada cukup artikel, sisa barisnya diisi
**artikel terbaru** dari arsip — bukan artikel lama yang angka dibacanya besar.
Jadi artikel lawas tidak akan pernah menempel di puncak lagi.

Rail ini dipakai di beranda, halaman pilar, hasil pencarian, dan halaman penulis.

### Profil penulis
**Pengguna → (pilih penulis) → bagian "GameIndo — Profil Penulis":**
peran/jabatan, jumlah artikel, sejak tahun, dibaca/bulan.

### Halaman & menu
- **Laman → Tambah Laman Baru** untuk halaman statis (Tentang, Kontak, dll).
- **Tampilan → Menu** untuk mengatur menu Header/Footer/Drawer. Tambahkan
  kategori, halaman, atau tautan khusus. Nav pilar tetap berwarna otomatis.

---

## Langkah berikutnya (go-live & Google)

Setelah situs live di domain, tahap SEO berikutnya (akan kita kerjakan terpisah):
- Pasang plugin SEO (mis. Yoast/Rank Math) untuk meta title/description & sitemap XML.
- Daftarkan situs ke **Google Search Console** dan kirim sitemap.
- Siapkan otomasi produksi artikel (via REST API / wp-cli / plugin) dengan
  keyword sesuai pilar — model kontennya sudah siap untuk itu.

---

## Kredensial demo lokal (lingkungan pengembangan)

Hanya untuk lingkungan uji lokal (Docker), **bukan** untuk produksi:
- URL: `http://localhost:8080` · Admin: `http://localhost:8080/wp-admin`
- User: `admin` · Password: `admin123`

Ganti password sebelum situs dipakai publik.
