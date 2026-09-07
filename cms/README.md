# GameIndo — Paket WordPress CMS

Konversi situs statis GameIndo menjadi **tema WordPress native** yang dikelola
sepenuhnya dari wp-admin. Ini folder kerja + hasil build.

## Struktur

```
cms/
├── themes/gameindo/          Tema WordPress (sumber)
│   ├── *.php                 Template: front-page, single, archive, author,
│   │                         search, page, index, 404, header, footer, searchform
│   ├── inc/                  Helper render (port dari templates.js) + nav walker
│   ├── js/theme.js           Interaktivitas (drawer, megamenu, load-more, dll)
│   └── assets/               CSS, gambar, dan data fallback (dari situs statis)
├── plugins/gameindo-core/    Plugin model konten (sumber)
│   ├── gameindo-core.php     Bootstrap + kategori pilar + menu admin
│   └── includes/             CPT widget, meta box artikel, profil penulis,
│                             meta widget esports, helper data, REST gameindo/v1
├── import/
│   ├── import.php            Importer wp-cli (artikel, penulis, media, widget,
│   │                         menu, halaman) — idempoten
│   └── gameindo-content.xml  Hasil export WXR konten demo
├── dist/                     Hasil build siap unggah
│   ├── gameindo-theme.zip
│   ├── gameindo-core-plugin.zip
│   └── gameindo-content.xml
├── docker-compose.yml        Lingkungan uji lokal (WordPress + MariaDB + wp-cli)
├── INSTALL-HOSTINGER.md       Panduan pasang di Hostinger  ← BACA INI untuk deploy
└── README.md                 (berkas ini)
```

## Arsitektur singkat

- **Pilar = Kategori.** Slug tetap: `video-games`, `esports`, `streamer`,
  `tech`, `entertainment`. Slug mengendalikan warna via `[data-pillar]` di CSS.
  Slug lama `home` (Video Game) tetap ada untuk artikel yang sudah terlanjur di
  sana, tapi bukan seksi tersendiri: `gameindo_canonical_pillar()` memetakannya
  ke `video-games`, dan `gameindo_nav_pillars()` — yang dipakai nav, footer,
  mega menu, tile, dan band beranda — mengeluarkannya dari daftar. Keduanya
  memakai merah induk yang sama, jadi satu beat tidak pernah tampil sebagai dua
  seksi berwarna beda. Tema membuat kategori pilar yang belum ada saat `init`
  (sekali, dijaga opsi `gameindo_pillar_terms`), supaya pilar yang lahir dari
  pembaruan tema tidak 404 di situs yang plugin-nya sudah lama aktif.
- **Pilar Video Games dirakit lintas kategori.** `gameindo_video_games_pool()`
  menggabungkan kategori `video-games`, kategori lama `home`, dan artikel
  konsol/handheld dari pilar lain, lalu diurutkan terbaru dulu — jadi halamannya
  berisi sejak hari pertama tanpa editor memindahkan apa pun. Klasifikasi
  konsol memakai pencocokan **kata utuh** (`gameindo_text_mentions()`) atas
  judul/ringkasan/subkategori/tag/kategori saja — bukan isi artikel, supaya satu
  penyebutan lewat tidak memindahkan artikel. Kata kuncinya lewat filter
  `gameindo_game_platforms`, kedalaman pool lewat `gameindo_video_games_pool`.
  Chip `?platform=` (PS5/PC/Xbox/Switch) menyaring halamannya, dan headline
  halaman memakai artikel **konsol** terbaru — itu slant pilarnya, bukan
  batasnya. Dua set kolektif di `gameindo_platform_keywords()` menjaga bedanya:
  `'any'` (semua platform) menentukan **apa yang masuk pool** dari pilar lain,
  `'console'` (grup ber-flag `console`) menentukan **apa yang jadi headline**.
  Dulu pool memakai set console, dan akibatnya chip PC menyaring daftar yang
  tidak mungkin memuat artikel PC. Kata generik "konsol"/"console" ikut di kedua
  set kolektif tapi tidak di chip mana pun — artikel yang cuma menyebut "konsol"
  itu liputan konsol, tapi bukan artikel PS5 atau Xbox.
- **Panel Rilis Mendatang (RAWG)** menggantikan Terpopuler di halaman Video
  Games: `gameindo_upcoming_games()` → `gameindo_core_get_upcoming_games()` di
  plugin, yang menembak `GET /api/games?dates=<hari ini>,<+N hari>&ordering=released`.
  Key disimpan di opsi `gameindo_rawg_key` atau konstanta `GAMEINDO_RAWG_KEY`,
  selalu server-side, cache *stale-while-revalidate* (TTL 6 jam, transient 24
  jam). **Opt-in:** tanpa key hasilnya array kosong dan `archive.php` jatuh ke
  rail Terpopuler — jadi memasang versi ini tidak mengubah apa pun sampai key
  diisi, dan API mati berarti daftar lawas, bukan panel kosong.
- **Tautan baris rilis.** Endpoint daftar tidak membawa `website`; hanya
  `/games/{id}` yang punya. Jadi setiap baris **selalu** dapat tujuan gratis
  lebih dulu — halaman game di RAWG, diturunkan dari `slug` tanpa request — lalu
  `gameindo_core_rawg_add_websites()` menaikkannya ke situs resmi untuk sebanyak
  judul yang muat dalam anggaran (`gameindo_rawg_detail_budget`, default 3 per
  rebuild). Jawaban per game di-cache **seminggu** pada kuncinya sendiri, jadi
  setelah lintasan pertama rebuild tidak berbiaya untuk judul yang sudah dikenal,
  dan judul yang belum terjangkau tetap punya tautan RAWG — panel tidak pernah
  tanpa tautan sambil terisi. Barisnya menyebut host tujuan sebelum diklik, sama
  seperti baris jadwal menyebut penyiarnya.
- **Pilar baru masuk ke menu lewat dua jalur.** Menu yang diatur di Tampilan →
  Menu menang atas nav otomatis, jadi pilar yang lahir dari pembaruan tema tidak
  akan terlihat justru di situs yang mengikuti panduan pasang. (1)
  `gameindo_menu_with_pillars()` menyisipkannya saat render, tanpa menulis
  apa pun, untuk `gameindo_required_nav_pillars()` yang belum ada di menu; ini
  berhenti sendiri begitu itemnya ada. (2) `gameindo_seed_pillar_menu_items()`
  menjadikannya **item menu sungguhan** di `primary`, `footer`, dan `drawer` —
  sekali jalan (opsi `gameindo_menu_seed`), supaya bisa diurutkan, diganti nama,
  atau dihapus dari wp-admin dan tetap hilang. Penempatannya di depan entri
  pilar pertama, jadi item ekor seperti "Cari" di drawer tetap paling akhir, dan
  menu yang sudah memuat kategori lama `home` **diarahkan ulang**, bukan
  ditambahi entri kedua untuk beat yang sama. `gameindo_retarget_menu_item()`
  mengirim balik seluruh field item (induk, kelas, target, deskripsi):
  `wp_update_nav_menu_item()` menulis ulang item dari argumen yang diberikan,
  jadi field yang tidak disertakan akan terhapus.
- **Meta artikel** (subkategori, waktu baca, jumlah dibaca, featured, spotlight)
  disimpan sebagai post meta `_gi_*`, diisi lewat meta box (plugin).
- **Widget esports** (ticker, topik, match, klasemen) = custom post type,
  dirender server-side oleh tema via helper plugin; ada fallback JSON di tema
  bila plugin nonaktif.
- **Jadwal match = PandaScore (live).** `includes/pandascore.php` mengambil
  `/{game}/matches/running` + `/{game}/matches/upcoming` untuk enam game
  (`mlbb`, `csgo`, `valorant`, `lol`, `dota2`, `ow` — perhatikan CS2 tetap
  memakai prefiks lama `csgo`, dan prefiks endpoint **berbeda** dari slug
  videogame seperti `cs-go`/`dota-2`). Semua di sisi server; token dari
  konstanta `GAMEINDO_PANDASCORE_TOKEN` atau opsi wp-admin.
  Cache-nya *stale-while-revalidate*: TTL 3 menit (live) / 15 menit (jadwal)
  menentukan kapan data dianggap basi, sementara transient-nya bertahan 24 jam
  supaya API mati berarti jadwal lawas — bukan panel kosong. Penyegaran
  dijalankan cron `gameindo_pandascore_cron` (tiap 5 menit); permintaan
  front-end paling banyak menembak 4 feed sinkron (filter
  `gameindo_pandascore_sync_budget`) agar cache dingin tidak memperlambat
  halaman. `tournament.tier` dipakai **hanya untuk memeringkat**, tidak pernah
  menyaring — Overwatch World Cup ber-tier `c` dan CS2 sering tidak punya event
  tier a/b sama sekali, jadi batas tier akan mengosongkan game. Tema memanggil
  `gameindo_get_schedule()`, yang jatuh ke CPT `gi_match` bila PandaScore kosong.
- **Kurasi panel beranda.** `gameindo_core_get_schedule()` menerima `tier_floor`
  dan `max_per_game`; beranda memakai `tier_floor` = `gameindo_prestige_floor()`
  (default `c`, filter `gameindo_prestige_tier_floor`) dan `max_per_game` = 2.
  Kunci urutan terluar adalah **gengsi, di atas status live** — kalau tidak,
  kualifikasi tertutup yang kebetulan live akan mengalahkan MPL dan LEC (kasus
  nyata: pada satu sore, satu-satunya match live di keenam game adalah empat
  kualifikasi CS2 tier-d). Keduanya **aturan lunak**: baris di bawah floor
  diturunkan lalu dipakai menambal, dan cap dilonggarkan bila variasi tidak
  cukup — jadi panel tidak pernah kosong atau bolong. Halaman Esports sengaja
  **tidak** memakai keduanya: di sana jadwal harus lengkap dan urut jam.
- **Panel Jadwal digulir di dalam panelnya** (`.gi-night-panel--schedule`, tinggi
  `clamp(300px, 58vh, 460px)` — 460px itu tinggi `.gi-feature` di sebelahnya).
  Dicetak utuh, satu matchday penuh membuat panel 2–3× lebih tinggi dari artikel
  utama: kolom kiri jadi lorong putih di desktop, dan di ponsel daftar artikel
  terkubur di bawah dinding baris. Judul hari `position:sticky` di dalam
  scroller, dan scroller-nya `tabindex="0"` + `role="region"` supaya bisa
  digulir dari keyboard. Karena tidak lagi dibatasi tinggi halaman, jumlah baris
  yang diambil dinaikkan 12 → 20.
- **Chip game di halaman Esports** menyaring panel Jadwal saja, lewat `?game=`
  (`gameindo_current_game()`), bukan daftar artikel. Panel Klasemen lama sudah
  digantikan; CPT `gi_standing` beserta `gameindo_get_standings()` dan
  `gameindo_standings_row()` sengaja **dibiarkan utuh tapi tak terpakai** supaya
  klasemen mudah dihidupkan lagi.
- **Live ticker = artikel terbaru.** `gameindo_get_ticker()` mengembalikan 12
  artikel terbit terakhir (terbaru dulu), menandai yang < 48 jam dengan badge
  "Baru". CPT `gi_ticker` **sengaja tidak lagi dirender** di header — menunya
  tetap ada di wp-admin tapi tidak memengaruhi tampilan. Kalau tidak ada pos
  sama sekali, ticker mengembalikan array kosong dan bar-nya disembunyikan.
- **Terpopuler = paling banyak dibaca dalam 7 hari.** `gameindo_trending_posts()`
  mengambil artikel dalam jendela 7 hari lalu mengurutkannya via
  `gameindo_rank_popular()`: `_gi_reads` terbesar dulu, **terbaru dulu bila
  seri**. Artikel tanpa `_gi_reads` bernilai 0, jadi situs yang tidak pernah
  mengisi kolom itu otomatis mendapat rail murni urut-terbaru — ini perilaku
  yang diinginkan, bukan kasus rusak. Bila jendela 7 hari kurang dari jumlah
  baris, sisanya **ditambal artikel terbaru di luar jendela**, bukan dengan
  memeringkat ulang seluruh arsip (itulah dulu penyebab artikel lawas ber-reads
  besar menempel di puncak). Jendela bisa diubah lewat filter
  `gameindo_popular_window_days`.
- **Responsif bertingkat** (di `assets/css/main.css`): `<=1100` rail menyempit
  + nav pilar dirapatkan, `<=900` rail menumpuk (tablet potret & layar dalam
  Galaxy Fold ~673px masuk sini) + nav dirapatkan lagi, `<=780` khusus nav
  (lebar terakhir di mana enam pilar masih muat), `<=720` spesifikasi
  ponsel asli (nav berganti drawer), `<=380` satu kolom untuk layar luar
  Fold (280–344px). Sejak pilar keenam masuk, `.gi-header__nav-wrap` juga
  `overflow-x:auto` dan `.gi-pillarnav` memakai `margin-inline:auto` alih-alih
  `justify-content:center` — baris flex yang di-*center* dan meluap memotong
  item pertamanya sendiri, yaitu pilar yang paling mungkin dicari pembaca.
  **Jangan menaruh `grid-template-columns` sebagai inline
  style di template** — inline style tidak bisa ditimpa media query, dan itulah
  yang dulu membuat halaman pilar/cari/penulis tetap dua kolom di ponsel sampai
  isinya tergencet hilang. Pakai kelas `.gi-rail-layout` (konten + rail 340px)
  atau `.gi-pillar-layout` (2fr/1fr). Catatan: override untuk `.gi-latest-layout`
  harus ditulis di `home.css`, bukan `main.css` — spesifisitasnya sama dan
  `home.css` dimuat belakangan.
- **Menu** header/footer/drawer memakai WP Menu (Tampilan → Menu) dengan walker
  khusus yang mempertahankan atribut `data-pillar`; bila menu belum diatur,
  nav otomatis dibangun dari kategori pilar.
- **Baris afiliasi grup di footer** (`.gi-footer__group`, di bawah garis
  pembatas tipis, rata kanan di desktop / rata kiri di ponsel): logo
  `assets/logo/popshck-logo.png` + teks "Part of … Group", tertaut ke
  `https://popshck.com`. Berkas logonya ditata di `footer.php`, bukan
  dikodekan mati — ganti file itu untuk mengganti logonya.
- Semua server-rendered (baik untuk SEO). Endpoint REST `gameindo/v1` tersedia
  bila kelak ingin dipakai headless.
- **SEO native, otomatis mengalah ke plugin.** `inc/seo.php` mencetak meta
  description, `canonical`, `robots`, Open Graph + Twitter Card lengkap, dan
  JSON-LD (`Organization`+`WebSite` di semua halaman, `NewsArticle` di
  artikel) lewat `wp_head` (prioritas 1 dan 2). `gameindo_seo_active()`
  mendeteksi Yoast/RankMath/AIOSEO/SEOPress (konstanta atau kelasnya) dan
  **mematikan seluruh output tema** kalau salah satu aktif, supaya tidak ada
  tag ganda — filter `gameindo_seo_disable` bisa memaksa nonaktif/aktif kalau
  perlu. Deskripsi turun dari ringkasan editor (bukan potongan konten mentah)
  lewat `gameindo_get_excerpt()`; gambar OG jatuh ke `gameindo-hero` artikel,
  lalu ke logo situs. Canonical membuang query pemfilteran (`?platform=`,
  `?game=`) supaya varian terfilter satu arsip tidak dianggap konten duplikat
  — halamannya tetap `index,follow`, cuma dikanonikalkan; hanya hasil
  pencarian yang `noindex,follow`. Font Google (`gameindo_fonts_url()`) dan
  `preconnect` ke `fonts.googleapis.com`/`fonts.gstatic.com` (filter
  `wp_resource_hints`) dipisah dari `main.css` supaya tidak ada rantai
  `@import` berurutan yang menunda render — dan slide pertama hero beranda
  dimuat *eager* + `fetchpriority="high"` (slide lain tetap `lazy`) supaya
  gambar LCP tidak berebut bandwidth dengan slide yang belum terlihat.

## Menjalankan lingkungan uji lokal

```bash
cd cms
docker compose up -d
docker compose exec wpcli wp core install \
  --url=http://localhost:8080 --title=GameIndo \
  --admin_user=admin --admin_password=admin123 \
  --admin_email=you@example.com --skip-email
docker compose exec wpcli wp plugin activate gameindo-core
docker compose exec wpcli wp theme activate gameindo
docker compose exec wpcli wp rewrite structure '/%postname%/' --hard
docker compose exec wpcli wp eval-file /import/import.php   # konten demo
```

Buka http://localhost:8080 (situs) dan http://localhost:8080/wp-admin
(admin: `admin` / `admin123`).

## Membangun ulang zip rilis

```bash
cd cms
(cd themes  && zip -rq ../dist/gameindo-theme.zip gameindo -x "*.DS_Store")
(cd plugins && zip -rq ../dist/gameindo-core-plugin.zip gameindo-core -x "*.DS_Store")
```
