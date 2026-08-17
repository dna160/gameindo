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

- **Pilar = Kategori.** Slug tetap: `home` (Video Game), `esports`, `streamer`,
  `tech`, `entertainment`. Slug mengendalikan warna via `[data-pillar]` di CSS.
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
- **Menu** header/footer/drawer memakai WP Menu (Tampilan → Menu) dengan walker
  khusus yang mempertahankan atribut `data-pillar`; bila menu belum diatur,
  nav otomatis dibangun dari kategori pilar.
- Semua server-rendered (baik untuk SEO). Endpoint REST `gameindo/v1` tersedia
  bila kelak ingin dipakai headless.

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
