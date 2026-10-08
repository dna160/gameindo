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
- **Hero beranda — panel Rilis Mendatang di sisi slider juga.** Kolom kanan
  hero (dulu 2 kartu "trending") sekarang memakai widget **Rilis Mendatang**
  yang sama persis dengan halaman Video Games — `gameindo_upcoming_games()`
  tanpa filter platform — dan jatuh ke kartu trending lama kalau RAWG kosong,
  persis pola *opt-in, degradasi anggun* yang sama. `.gi-hero__main` adalah
  wrapper baru yang menampung slider **dan** `.gi-hero__more` (3 kartu berita
  kecil) — sebelum ini, kolom kiri hero cuma berisi slider yang tingginya
  tetap (`.gi-feature { height:460px }`), sementara grid `.gi-hero__grid`
  men-stretch BARIS ke tinggi kolom kanan yang lebih tinggi (panel rilis +
  Jadwal Match bisa jauh lebih tinggi dari 460px) — sisa tingginya dulu cuma
  kotak hitam kosong di bawah slider. `.gi-hero__more` adalah sel `flex:1`
  yang mengisi persis sisa ruang itu dengan artikel nyata (bukan slider yang
  di-stretch jadi lebih tinggi — itu akan meng-crop foto hero jauh lebih
  agresif dari yang dimaksud). Tiga artikel yang dipakainya dikeluarkan dari
  daftar exclude sebelum "Latest News" dihitung, jadi tidak ada artikel yang
  tampil dua kali di beranda.
- **`minmax(0, 1fr)`, bukan `1fr` polos, di semua `.gi-grid-*`.** Track grid
  `1fr` punya minimum tersirat "auto" (lebar min-content kontennya sendiri) —
  jadi kartu/tile dengan teks yang tidak bisa dipotong cukup panjang (mis.
  tile pilar "1,167 artikel →") menolak menyusut bersama kolomnya dan meluber.
  `body` sudah pakai `overflow-x:hidden` sehingga ini tidak pernah tampil sebagai
  scrollbar — melubernya jadi **konten yang diam-diam terpotong** di lebar
  sempit (≤380px, termasuk cover screen Galaxy Z Fold), yang lebih berbahaya
  karena tidak ada petunjuk apa pun bahwa itu terjadi.
- **Beranda hanya menampilkan berita yang benar-benar soal game — kanal
  sendiri tetap tampil apa adanya.** "Latest News" dan keempat band pilar
  non-Video-Games (Esports/Streamer/Tech/Entertainment) di beranda disaring
  lewat `gameindo_is_about_games()` (`inc/template-helpers.php`) sebelum
  dirender; halaman arsip pilar sendiri (`archive.php`) **tidak disentuh** —
  tetap menampilkan semua yang benar-benar difile di sana, tak tersaring,
  karena di situlah pembaca pergi untuk keluasan penuh pilarnya (termasuk,
  misalnya, berita tech umum yang di-file di Tech). Video Games dan Esports
  lolos otomatis lewat pilarnya saja (`gameindo_pillar_implies_gaming()`) —
  keduanya memang soal game secara definisi; tiga pilar lain dicek lewat
  `gameindo_gaming_keywords()` (kata umum seperti "game"/"gaming"/"esports",
  seluruh `gameindo_platform_keywords('any')`, plus judul waralaba besar
  seperti Genshin/Valorant/MLBB) atas `gameindo_post_signal_text()` yang
  sama dipakai `gameindo_is_console_post()` — heuristik kata kunci terbaik
  yang bisa dicapai, bisa saja salah menilai artikel yang tidak pernah
  menyebut nama game secara eksplisit. Band non-Video-Games mengambil lebih
  banyak kandidat (`posts_per_page => 20`) sebelum disaring ke 4; artikel yang
  lolos `gameindo_is_about_games()` memimpin band-nya, tapi kalau yang lolos
  kurang dari 4 (paling sering kena Streamer), sisa slotnya ditambal dari
  kandidat yang sama tanpa filter — band tetap tampil penuh 4 kartu, bukan
  setengah kosong, karena kurasi ini preferensi tampilan beranda, bukan aturan
  mutlak seperti di Latest News (yang memang bisa tampil kurang dari 4 kalau
  kandidatnya tidak cukup). Strip "berita lainnya" di bawah slider hero
  (`.gi-hero__more`, 3 kartu) ikut disaring dengan filter yang sama — tanpa
  tambalan, karena posisinya tepat di bawah hero yang sudah bertema game.
  **Belum tercakup** (sengaja, sesuai permintaan aslinya): hero slider sendiri,
  panel trending di sisinya, dan rail Terpopuler — ketiganya tetap apa adanya.
- **Panel Rilis Mendatang di beranda: teks putih, dan sekarang tampil juga
  di mobile.** Markup `.gi-night-panel--release` dipakai apa adanya dari
  `archive.php`, lengkap dengan warna `.gi-release__name { color: var(--ink) }`
  (nyaris hitam) — benar di halaman Video Games karena di sana panelnya duduk
  di atas `--surface` (putih), tapi di beranda panel itu duduk di dalam
  `.gi-hero` yang `--night` (nyaris hitam juga), dan barisnya sendiri tidak
  punya background — jadilah teks gelap di atas latar gelap. Diperbaiki
  dengan override yang di-scope ke `#gi-hero-side .gi-night-panel--release`
  saja (nama, platform, countdown, border, footer sumber data, semua pindah
  ke palet `--on-night`/`--on-night-muted` yang sudah dipakai `.gi-matchpanel`
  di sebelahnya) — halaman arsip pilar sama sekali tidak tersentuh. Widgetnya
  sendiri dulu ikut lenyap di `≤720px` bersama seluruh `.gi-hero__side`
  (`display:none`); sekarang ada jalur mobile terpisah — strip geser
  horizontal baru `.gi-mobile-releases` (kartu `gameindo_mobile_release_card()`
  di `inc/template-helpers.php`, pola yang sama persis dengan
  `.gi-mobile-matches`/`gameindo_mobile_match_card()` yang sudah ada untuk
  jadwal match) — jadi jadwal rilis game dan jadwal pertandingan sama-sama
  bisa digeser di mobile, bukan cuma salah satunya. `.gi-hero__more` (3 kartu
  "berita lainnya" di bawah slider) yang tadinya menyusun ulang jadi 2 baris
  di layar sempit sekarang juga jadi strip geser horizontal `scroll-snap`
  pada breakpoint yang sama, supaya tinggi beranda tidak melonjak di mobile.
  Sekalian ditemukan dan diperbaiki satu bug `1fr` polos lagi (lihat poin
  `minmax(0, 1fr)` di atas) yang luput sebelumnya: `.gi-hero__grid` dan
  `.gi-latest-layout` di breakpoint `≤720px` — ini yang membuat beranda
  meluber horizontal persis di lebar cover screen Galaxy Z Fold (~344px).
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
- **Gambar unggulan tidak dobel di body artikel.** ARTICLE-CONTRACT.md §1
  meminta copywriter tidak menaruh featured image sebagai gambar inline
  pertama di body — tapi tidak semua artikel lahir dari pipeline itu.
  `gameindo_dedupe_featured_image_from_content()` (`inc/template-helpers.php`)
  membandingkan elemen **paling depan** body (`<figure>`/`<p>`/`<img>` polos)
  dengan featured image-nya lewat `gameindo_image_basename_key()` — nama
  berkas setelah akhiran ukuran otomatis WordPress dibuang (`shot-1024x576.jpg`
  dan `shot-300x169.jpg` sama-sama jadi kunci `shot.jpg`) — plus kelas
  `wp-image-{ID}` bawaan block editor. Sengaja **bukan** mencocokkan URL utuh:
  itu gagal untuk ukuran crop yang tidak terdaftar di tema atau URL yang
  ditulis ulang CDN/plugin optimasi gambar, dan filename-based matching tahan
  ke keduanya (host boleh beda, hanya nama file di ujung path yang dibaca).
  **Kasus kedua** (ditemukan dari artikel Asian Games): artikel hasil tulis
  ulang dari sumber luar bisa mengunggah foto sumbernya sebagai featured
  image tapi meninggalkan `<img>` hotlink **asli** ke CDN situs sumber di
  body — beda nama file dan beda domain sama sekali, jadi pencocokan nama
  file tidak mungkin menangkapnya. Karena ARTICLE-CONTRACT.md §1 memang
  mengharuskan semua gambar body diunggah ke media library situs sendiri,
  gambar terdepan yang host-nya **bukan domain situs** dianggap pelanggaran
  dengan sendirinya dan dibuang — tanpa perlu tahu itu foto yang sama persis
  atau bukan (tidak ada cara membandingkan piksel tanpa mengambil berkas
  eksternal itu). "Terdepan" mentolerir sedikit gangguan sebelum gambarnya —
  ditemukan dari artikel Shueisha TGS 2026: baris judul yang bocor terulang
  (`<p><strong>Judul:</strong> …</p>`) lalu `<h1>` nyasar di body (yang
  itu sendiri sudah pelanggaran kontrak — `<h1>` cuma boleh jadi judul pos)
  sebelum gambar dobelnya. Keduanya dilewati **apa adanya** (tidak ikut
  dihapus) selagi mencari gambarnya; begitu ketemu elemen lain yang bukan
  `<h1>`–`<h6>` atau paragraf berbentuk label singkat ("Kata:" di awal), 
  pemindaian berhenti — jadi paragraf pembuka yang sungguh-sungguh isi
  artikel tidak pernah salah dianggap "gangguan" dan melewati gambar yang
  sengaja dipakai ulang di baliknya. Kalau cocok (lewat salah satu jalur),
  elemen gambarnya saja yang dibuang — kemunculan ulang foto yang sama **di
  tengah artikel**, atau gambar eksternal yang **bukan** elemen terdepan,
  dibiarkan, karena itu penulis sengaja merujuknya, bukan duplikat produksi.
  Dipanggil dari `single.php` sebagai pengganti `the_content()` langsung.
- **Thumbnail yang gagal termuat otomatis diganti placeholder.**
  `gameindo_image_url()` sudah lama menyediakan placeholder pillar-netral
  (`assets/samples/ph-neutral-1.png`) untuk pos **tanpa** featured image —
  tapi pos yang **punya** featured image dengan berkas media rusak/hilang
  (media terhapus, gagal upload, URL RAWG mati) tetap mencetak `<img>` yang
  404 di browser. Setiap `<img>` gambar-post (card, feature, rank-row, art
  RAWG, hero artikel) sekarang membawa `data-gi-fallback`; `theme.js`
  memasang satu listener `error` di `document` (capture phase, karena event
  itu tidak *bubble*) yang menukar `src`-nya ke placeholder begitu gambar
  gagal dimuat — mencakup kartu yang datang belakangan lewat "Muat Lebih
  Banyak" juga, tanpa perlu tahu pos mana saja yang datanya rusak.
- **Baris afiliasi grup di footer** (`.gi-footer__group`, di bawah garis
  pembatas tipis, rata kanan di desktop / rata kiri di ponsel): logo
  `assets/logo/popshck-logo.png` + teks "Part of … Group", tertaut ke
  `https://popshck.com`. Berkas logonya ditata di `footer.php`, bukan
  dikodekan mati — ganti file itu untuk mengganti logonya.
- Semua server-rendered (baik untuk SEO). Endpoint REST `gameindo/v1` tersedia
  bila kelak ingin dipakai headless.
- **Verifikasi Google Search Console** — kolom ketiga di wp-admin →
  GameIndo → Analytics (atau konstanta `GAMEINDO_GSC_VERIFICATION`):
  `gameindo_core_gsc_verification()` menyimpannya, `gameindo_seo_search_console_tag()`
  (tema, `inc/seo.php`) mencetak `<meta name="google-site-verification">`
  di `wp_head`. Sengaja **tidak** ikut mengalah ke `gameindo_seo_active()` —
  ini membuktikan kepemilikan domain, bukan tag SEO yang bersaing, dan
  beberapa tag `google-site-verification` sekaligus di satu halaman itu
  wajar (masing-masing membuktikan properti Search Console yang beda), jadi
  tetap tayang meski nanti Yoast/RankMath dipasang. Sanitizer-nya menerima
  potongan kode maupun string utuh `google-site-verification=KODE` yang
  disalin langsung dari Search Console.
- **Google Tag Manager + GA4 langsung** — dua kolom independen di wp-admin →
  GameIndo → Analytics (atau konstanta `GAMEINDO_GTM_ID`/`GAMEINDO_GA4_ID`):
  Container ID GTM dan/atau Measurement ID GA4. `gameindo_core_gtm_id()` /
  `gameindo_core_ga4_id()` (plugin, `includes/analytics.php`) menyimpan/
  mengambilnya; `inc/analytics.php` (tema) mencetak snippet resmi masing-masing
  apa adanya di `wp_head` (prioritas 0, sepagi mungkin) — GTM juga di
  `wp_body_open` (hook inti WP, sudah dipanggil di `header.php` persis untuk
  kasus ini) untuk fallback `<noscript>`. Keduanya independen dan boleh aktif
  bersamaan (mis. GA4 langsung + GTM kosong tag untuk nanti dipakai tag lain);
  yang **tidak boleh** adalah mengisi GA4 langsung DAN menambahkan tag GA4
  Configuration untuk properti yang sama di dalam GTM — itu bikin setiap
  pageview kehitung dua kali, sudah diperingatkan di halaman pengaturannya.
  Sama seperti `gameindo_seo_active()`, `gameindo_gtm_active()`/
  `gameindo_ga4_active()` **otomatis mati** kalau plugin analytics lain (Site
  Kit, GTM4WP, MonsterInsights/ExactMetrics, Analytify) terdeteksi aktif,
  supaya tag yang sama tidak terpasang dobel dan menggandakan hitungan.
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
  pencarian yang `noindex,follow`. Juga mencetak `<meta name="author">`,
  `<meta name="keywords">` (dari tag/subkategori/pilar artikel — sumbernya
  taksonomi asli, bukan mengarang; Google/Bing sendiri sudah mengabaikan tag
  ini untuk peringkat sejak ~2009, tapi beberapa alat audit SEO masih
  mengeceknya, dan tag yang benar-tapi-tidak-berpengaruh tidak merugikan),
  `<meta name="publisher">`, serta `article:author`/`article:publisher` OG
  untuk artikel. Separator judul (`document_title_separator`) diganti jadi
  em dash (`—`) supaya senada dengan tipografi judul di tempat lain situs.
  **`robots.txt`** (filter `robots_txt`) secara eksplisit meng-*allow*
  Googlebot/Bingbot (lewat wildcard `User-agent: *`) **dan** crawler
  AI/answer-engine utama (GPTBot, ChatGPT-User, OAI-SearchBot,
  Google-Extended, ClaudeBot, anthropic-ai, PerplexityBot, CCBot,
  Applebot-Extended) satu per satu — supaya niat situs untuk di-crawl dan
  dikutip mesin AI itu eksplisit, bukan cuma "kebetulan tidak diblokir".
  Hasil pencarian **sengaja tidak** di-*disallow* di robots.txt meski
  `noindex` — men-block sekaligus mengandalkan `noindex` adalah jebakan SEO
  klasik: kalau di-block, Google tidak pernah mengambil halamannya sama
  sekali, jadi tidak pernah melihat tag `noindex`-nya, dan URL itu bisa
  tetap muncul di hasil pencarian tanpa cuplikan berdasarkan tautan yang
  mengarah ke sana. **`/sitemap.xml`** (hook `template_redirect`) me-301
  ke `/wp-sitemap.xml` — sitemap XML bawaan WordPress sendiri (aktif
  default sejak WP 5.5, tanpa plugin), yang sudah otomatis memenuhi semua
  yang biasanya diminta: artikel baru langsung muncul (berbasis query, bukan
  file cache), terpisah per jenis konten **dan** per taksonomi (jadi pilar —
  yang memang kategori — dapat sub-sitemap sendiri), dan `<lastmod>` dibaca
  langsung dari `post_modified_gmt` sehingga berubah begitu artikel diedit.
  Ketiga tambahan ini (robots.txt, alias sitemap, separator judul) sama-sama
  mengalah kalau `gameindo_seo_active()` mati (plugin SEO aktif), dan
  robots.txt/sitemap juga menghormati pengaturan "Discourage search
  engines" WordPress sendiri kalau itu dicentang. Font Google (`gameindo_fonts_url()`) dan
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
