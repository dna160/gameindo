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
