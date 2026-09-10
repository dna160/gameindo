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

Saat aktif, plugin otomatis membuat kategori pilar dengan slug tetap:
`video-games` (Video Games), `esports`, `streamer`, `tech`, `entertainment`,
ditambah `home` (Video Game) — slug lama yang dipakai artikel-artikel sebelum
pilar Video Games ada. **Nama boleh diubah; slug jangan diubah** — slug inilah
yang mengendalikan warna tiap pilar.

Kalau plugin sudah aktif sejak sebelum ada pilar Video Games, tema membuat
kategori yang kurang saat pertama kali dimuat — tidak ada langkah manual.

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
- **Panel Jadwal bisa digulir.** Isinya sampai 20 pertandingan, tapi tingginya
  dibatasi agar sejajar dengan artikel utama di sebelahnya — jadi halaman tidak
  memanjang ke bawah dan tidak menyisakan area kosong. Judul hari ikut menempel
  di atas saat digulir, jadi Anda selalu tahu baris itu hari apa. Di ponsel
  batasnya mengecil mengikuti tinggi layar.

### Pilar Video Games
Menu header kini punya entri **Video Games** dengan halamannya sendiri di
`/category/video-games/`. Halaman itu tidak menunggu Anda memindahkan artikel:
isinya dirakit dari tiga sumber sekaligus —

1. kategori **Video Games** (apa pun yang Anda taruh di sana selalu masuk),
2. kategori lama **Video Game** (`home`), yaitu liputan game yang sudah ada, dan
3. **artikel konsol/handheld dari pilar lain** — misalnya ulasan ROG Ally yang
   terlanjur masuk Tech.

Yang jadi *headline* halaman adalah artikel konsol terbaru, sesuai fokus pilar
ini. Chip **Semua / PS5 / PC / Xbox / Switch** menyaring halaman lewat URL
(`?platform=ps5`) jadi bisa dibagikan. Kalau sebuah chip masih kosong, itu
berarti belum ada artikel yang menyebut platform itu — halamannya menampilkan
pesan dan tautan kembali ke Semua, bukan halaman kosong.

### Panel "Rilis Mendatang" (RAWG)
Di halaman Video Games, panel kanan menampilkan game yang akan rilis beberapa
bulan ke depan, diurutkan dari yang paling dekat, lengkap dengan hitungan
mundur. Datanya dari **RAWG**.

1. Ambil API key gratis di [rawg.io/apidocs](https://rawg.io/apidocs).
2. wp-admin → **GameIndo → RAWG** → tempel key → Simpan.

Tiap baris bisa diklik: ke **situs resmi game** kalau RAWG tahu alamatnya,
kalau belum ke halaman game di RAWG. Nama tujuannya dicetak kecil di sebelah
tanda ↗ supaya pembaca tahu ke mana perginya, dan semuanya terbuka di tab baru.
Situs resmi terisi bertahap — tiap penyegaran menaikkan beberapa judul dan
hasilnya disimpan seminggu, jadi tidak ada lonjakan permintaan ke RAWG.

**Kalau key dikosongkan, panelnya tidak muncul** dan halaman kembali memakai
panel *Terpopuler* seperti sebelumnya — jadi ini aman dipasang lebih dulu dan
diisi belakangan. Chip platform ikut menyaring panelnya. Kalau RAWG sedang mati,
yang tampil daftar terakhir yang tersimpan, bukan panel kosong.

Penentuan konsol/handheld dibaca dari **judul, ringkasan, subkategori, tag, dan
kategori** — bukan isi artikel, supaya satu penyebutan di tengah tulisan tidak
memindahkan artikel ke pilar lain. Mau menambah kata kunci (konsol baru, merek
handheld baru)? Filter di `functions.php`:

```php
add_filter( 'gameindo_game_platforms', function ( $groups ) {
    $groups['konsol']['keywords'][] = 'steam machine';
    return $groups;
} );
```

Artikel lama berkategori **Video Game** tetap di tempatnya dan tetap berwarna
merah — di situs, keduanya tampil sebagai satu pilar **Video Games**. Kalau
mau merapikan, cukup pindahkan artikelnya ke kategori Video Games; tampilannya
tidak berubah.

#### Apa yang terjadi pada menu Anda saat pembaruan
Menu yang sudah Anda atur di **Tampilan → Menu** menang atas nav otomatis, jadi
tema merapikannya sendiri **satu kali** saat pertama dimuat setelah pembaruan:

| Menu | Yang terjadi |
|---|---|
| **Pilar Utama** (header) | Item **Video Games** ditambahkan tepat setelah "Home" |
| **Menu Mobile** (drawer) | Ditambahkan di antara pilar — item ekor seperti "Cari" tetap paling bawah |
| **Footer** | Item lama **Video Game** *diarahkan ulang* ke kategori Video Games, bukan ditambahi entri kedua |

Ini **item menu sungguhan**: bisa Anda urutkan ulang, ganti namanya, atau hapus
dari wp-admin seperti item lain. Karena hanya berjalan sekali, kalau Anda
menghapusnya ia tidak akan muncul lagi. Label yang sudah Anda tulis sendiri di
item footer tidak diubah — yang diganti hanya kalau labelnya masih "Video Game"
bawaan.

Kalau lokasi menunya belum diatur sama sekali, tidak ada yang ditulis: nav
otomatis memang sudah memuat Video Games.

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

### SEO (bawaan tema — tidak perlu plugin untuk mulai)
Tema sudah mencetak sendiri, di setiap halaman, tanpa perlu diatur:
- **Meta tag**: `<title>`, `description`, `keywords`, `author`, `publisher`,
  `canonical`, `robots`.
- **Open Graph + Twitter Card** lengkap (`article:author`/`article:publisher`
  di artikel) — tautan yang dibagikan ke Facebook/Twitter/WhatsApp tampil
  dengan judul, ringkasan, dan gambar yang benar.
- **JSON-LD** (`Organization`, `WebSite`, `NewsArticle` di tiap artikel) —
  dibaca Google untuk kartu artikel & breadcrumb di hasil pencarian.
- **`robots.txt`** (`gameindo.com/robots.txt`) — meng-*allow* Googlebot,
  Bingbot, dan crawler AI utama (GPTBot, ClaudeBot, Google-Extended,
  PerplexityBot, dll.) secara eksplisit, plus baris `Sitemap:`.
- **Sitemap XML** — sitemap bawaan WordPress sendiri (`/wp-sitemap.xml`,
  aktif otomatis sejak WP 5.5, tanpa plugin), dengan alias di
  `gameindo.com/sitemap.xml` supaya URL konvensionalnya juga jalan. Artikel
  baru otomatis muncul, terpisah per kategori/pilar, dan tanggal
  `lastmod`-nya berubah sendiri begitu artikel diedit — tidak perlu diatur.

Kalau nanti **Yoast SEO / Rank Math / All in One SEO / SEOPress** dipasang
dan diaktifkan, tema otomatis mendeteksinya dan **mematikan seluruh output
SEO-nya sendiri** (termasuk robots.txt dan alias sitemap) — tidak akan ada
tag ganda atau bentrok, tinggal pasang plugin dan atur dari sana seperti
biasa. Yang belum ada di sini (dan baru datang kalau plugin SEO dipasang):
editor meta title/description manual per-artikel di luar ringkasan editor.

**Submit sitemap ke Google Search Console**: gunakan URL
`https://gameindo.com/wp-sitemap.xml` (bukan `/sitemap.xml`) saat submit di
Search Console — itu lokasi sitemap yang sebenarnya; `/sitemap.xml` cuma
alias yang mengarah ke sana.

### Analytics (Google Tag Manager + GA4 — bawaan tema, tanpa plugin)
**GameIndo → Analytics** di wp-admin: dua kolom independen, isi salah satu
atau keduanya.
- **Container ID GTM** (`GTM-XXXXXXX`) — memasang Google Tag Manager. Tag
  apa pun (GA4, Meta Pixel, dll.) lalu diatur di dashboard
  tagmanager.google.com, tanpa upload tema ulang tiap kali menambah tag baru
  (tinggal **Submit → Publish** di sana).
- **Measurement ID GA4** (`G-XXXXXXXXXX`) — memasang GA4 langsung
  (`gtag.js` resmi Google), tanpa perlu menyentuh dashboard GTM sama sekali.
  Paling cepat kalau cuma butuh GA4.

**Jangan isi keduanya untuk properti GA4 yang sama** — kalau Measurement ID
GA4 di atas terisi, dan *nanti* sebuah tag GA4 Configuration untuk properti
yang sama juga ditambahkan di dalam GTM, setiap pageview tercatat dua kali.
Pilih satu jalur untuk satu properti GA4.

Belum punya akun GTM/GA4? Halaman **GameIndo → Analytics** itu sendiri
berisi langkah-langkah lengkap bikin akun GTM, bikin properti GA4, dan dua
opsi menyambungkannya (langsung lewat kolom GA4, atau lewat tag di dalam
GTM). Kalau plugin analytics lain (Site Kit, GTM4WP, MonsterInsights, dsb.)
sedang aktif, tema otomatis mengalah dan tidak memasang snippetnya sendiri,
supaya tidak ada tag dobel.

---

## Langkah berikutnya (go-live & Google)

Setelah situs live di domain:
- (Opsional) Pasang plugin SEO (mis. Yoast/Rank Math) kalau butuh sitemap XML
  atau ingin menulis meta title/description manual per-artikel — tema akan
  otomatis mengalah begitu plugin itu aktif (lihat bagian SEO di atas).
- Isi Container ID di **GameIndo → Analytics** untuk mengaktifkan Google Tag
  Manager (lihat bagian Analytics di atas untuk langkah bikin akun GTM/GA4).
- Daftarkan situs ke **Google Search Console** dan kirim sitemap.
- Siapkan otomasi produksi artikel (via REST API / wp-cli / plugin) dengan
  keyword sesuai pilar — model kontennya sudah siap untuk itu.

---

## Kredensial demo lokal (lingkungan pengembangan)

Hanya untuk lingkungan uji lokal (Docker), **bukan** untuk produksi:
- URL: `http://localhost:8080` · Admin: `http://localhost:8080/wp-admin`
- User: `admin` · Password: `admin123`

Ganti password sebelum situs dipakai publik.
