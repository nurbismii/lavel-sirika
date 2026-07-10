# SIRIKA

SIRIKA adalah Sistem Rute Izin Kendaraan untuk mengelola izin masuk kendaraan, import data Excel, review dan aktivasi izin, QR digital/fisik, scan security, peta rute, dashboard operasional, dan laporan Excel.

## Stack

- PHP 7.4
- Laravel 8
- Blade
- Alpine.js
- Leaflet.js
- MySQL atau PostgreSQL
- Laravel Excel
- BaconQrCode
- html5-qrcode

## Modul Utama

- Auth dan role: `super_admin`, `admin_hr`, `security`, `auditor`
- Import Excel izin kendaraan
- Review dan aktivasi izin
- QR permit digital dan kartu cetak
- Scan QR oleh security
- Peta rute dan highlight segmen
- Laporan izin dan laporan scan
- Dashboard operasional
- CRUD user

## Production

Production URL: `https://sirika.vdnisite.com`

Deployment cPanel menggunakan struktur aman:

- Source Laravel lengkap berada di luar `public_html`.
- Public web root berisi isi folder `public/` di `public_html/prod-sirika`.

Panduan deployment:

- `docs/deployment/CPANEL-PRODUCTION.md`

Template environment production:

- `.env.production.example`

## Local Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

## Testing

```bash
php artisan test
npm audit --omit=dev
composer audit
```

Catatan: `composer audit` masih dapat melaporkan risiko baseline Laravel 8. Upgrade dependency mayor ditangani di phase terpisah.
