# SIRIKA Phase 6 cPanel Production Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden SIRIKA for shared hosting cPanel production deployment at `https://sirika.vdnisite.com` without upgrading Laravel/PHP or changing business workflows.

**Architecture:** Keep application behavior stable and add deployment safety around it: production env templates, cPanel documentation, cacheable routes, host/CORS/session hardening, and verification tests. Avoid framework upgrades in this phase because the current production target is PHP 7.4 and Laravel 8.

**Tech Stack:** PHP 7.4, Laravel 8.83.29, Blade, Alpine.js, Leaflet.js, Laravel Excel, BaconQrCode, html5-qrcode, shared hosting cPanel.

## Global Constraints

- Production URL is `https://sirika.vdnisite.com`.
- cPanel public web root is `public_html/prod-sirika`.
- `public_html/prod-sirika` contains only the contents of Laravel `public/`.
- Full Laravel source lives outside `public_html`.
- cPanel Terminal/SSH is available.
- Do not upgrade Laravel, PHP, SwiftMailer, CORS package, or other major dependencies in Phase 6.
- Do not add database migrations unless a task proves it is unavoidable.
- Do not change import, permit, review, QR, scan, route-map, report, or export business behavior.
- Do not commit secrets, real database credentials, production app key, or passwords.
- Keep all implementation compatible with PHP 7.4 and Laravel 8.

---

## File Structure

Create:

- `.env.production.example`: production-safe environment template for cPanel.
- `docs/deployment/CPANEL-PRODUCTION.md`: operator deployment and rollback guide.
- `tests/Feature/ProductionEnvironmentTemplateTest.php`: checks production env template.
- `tests/Feature/DeploymentDocumentationTest.php`: checks deployment docs and README content.
- `tests/Feature/ProductionHardeningConfigTest.php`: checks TrustHosts, session, and CORS hardening.
- `tests/Feature/ProductionCacheCommandTest.php`: checks config/route/view cache commands.
- `app/Http/Controllers/HomeController.php`: cacheable replacement for root closure redirect.
- `app/Http/Controllers/Api/AuthenticatedUserController.php`: cacheable replacement for default `api/user` closure.

Modify:

- `.env.example`: make local example SIRIKA-specific and clearly non-production.
- `README.md`: replace Laravel default readme with SIRIKA project/deployment summary.
- `app/Http/Kernel.php`: enable `TrustHosts` in global middleware.
- `app/Http/Middleware/TrustHosts.php`: read configured trusted hosts.
- `config/sirika.php`: add `trusted_hosts` config.
- `config/session.php`: make `same_site` configurable via `SESSION_SAME_SITE`.
- `config/cors.php`: restrict default CORS config for this web app.
- `routes/web.php`: replace `/` closure with `HomeController`.
- `routes/api.php`: replace `/user` closure with `AuthenticatedUserController`.

---

### Task 1: Production Environment Template

**Files:**
- Create: `.env.production.example`
- Modify: `.env.example`
- Create: `tests/Feature/ProductionEnvironmentTemplateTest.php`

**Interfaces:**
- Consumes: current Laravel env conventions and `config/sirika.php` keys from earlier phases.
- Produces: `.env.production.example` with required production keys for later docs and deployment tests.

- [ ] **Step 1: Write failing production environment template test**

Create `tests/Feature/ProductionEnvironmentTemplateTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionEnvironmentTemplateTest extends TestCase
{
    /** @test */
    public function production_environment_template_contains_required_safe_defaults()
    {
        $path = base_path('.env.production.example');

        $this->assertFileExists($path);

        $values = $this->parseEnvFile($path);

        $requiredKeys = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'APP_DEBUG',
            'APP_URL',
            'LOG_CHANNEL',
            'LOG_LEVEL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'CACHE_DRIVER',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'SESSION_LIFETIME',
            'SESSION_SECURE_COOKIE',
            'SESSION_SAME_SITE',
            'SIRIKA_SEED_USER_PASSWORD',
            'SIRIKA_TRUSTED_HOSTS',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $values, "Missing {$key} in .env.production.example");
        }

        $this->assertSame('SIRIKA', $values['APP_NAME']);
        $this->assertSame('production', $values['APP_ENV']);
        $this->assertSame('', $values['APP_KEY']);
        $this->assertSame('false', $values['APP_DEBUG']);
        $this->assertSame('https://sirika.vdnisite.com', $values['APP_URL']);
        $this->assertSame('daily', $values['LOG_CHANNEL']);
        $this->assertSame('warning', $values['LOG_LEVEL']);
        $this->assertSame('true', $values['SESSION_SECURE_COOKIE']);
        $this->assertSame('lax', $values['SESSION_SAME_SITE']);
        $this->assertSame('sirika.vdnisite.com', $values['SIRIKA_TRUSTED_HOSTS']);

        $this->assertNotSame('laravel', strtolower($values['DB_DATABASE']));
        $this->assertNotSame('root', strtolower($values['DB_USERNAME']));
        $this->assertSame('', $values['DB_PASSWORD']);
    }

    /** @test */
    public function local_environment_example_is_not_presented_as_production_ready()
    {
        $path = base_path('.env.example');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $values = $this->parseEnvFile($path);

        $this->assertSame('SIRIKA', $values['APP_NAME'] ?? null);
        $this->assertSame('local', $values['APP_ENV'] ?? null);
        $this->assertSame('true', $values['APP_DEBUG'] ?? null);
        $this->assertStringContainsString('Use .env.production.example for cPanel production', $contents);
    }

    private function parseEnvFile(string $path): array
    {
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, "\"'");
        }

        return $values;
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
php artisan test --filter=ProductionEnvironmentTemplateTest
```

Expected: FAIL because `.env.production.example` does not exist and `.env.example` still has Laravel defaults.

- [ ] **Step 3: Add `.env.production.example`**

Create `.env.production.example`:

```dotenv
APP_NAME=SIRIKA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://sirika.vdnisite.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@sirika.vdnisite.com
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

SIRIKA_SEED_USER_PASSWORD=
SIRIKA_TRUSTED_HOSTS=sirika.vdnisite.com
SIRIKA_ROUTE_MAP_KEY=vdni-road-map-v1
SIRIKA_ROUTE_MAP_IMAGE_URL=/images/maps/vdni-road-map-v1.png
SIRIKA_ROUTE_MAP_WIDTH=3370
SIRIKA_ROUTE_MAP_HEIGHT=2384

CORS_PATHS=
CORS_ALLOWED_ORIGINS=https://sirika.vdnisite.com
CORS_ALLOWED_METHODS=GET,POST,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,X-Requested-With,X-CSRF-TOKEN,Authorization
```

- [ ] **Step 4: Update `.env.example` for local SIRIKA development**

Replace `.env.example` with:

```dotenv
# Local development example for SIRIKA.
# Use .env.production.example for cPanel production.

APP_NAME=SIRIKA
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lavel-sirika
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

SIRIKA_SEED_USER_PASSWORD=password
SIRIKA_TRUSTED_HOSTS=localhost,127.0.0.1,sirika.vdnisite.com
SIRIKA_ROUTE_MAP_KEY=vdni-road-map-v1
SIRIKA_ROUTE_MAP_IMAGE_URL=/images/maps/vdni-road-map-v1.png
SIRIKA_ROUTE_MAP_WIDTH=3370
SIRIKA_ROUTE_MAP_HEIGHT=2384

CORS_PATHS=
CORS_ALLOWED_ORIGINS=http://localhost,http://127.0.0.1:8000
CORS_ALLOWED_METHODS=GET,POST,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,X-Requested-With,X-CSRF-TOKEN,Authorization
```

- [ ] **Step 5: Run test and verify it passes**

Run:

```bash
php artisan test --filter=ProductionEnvironmentTemplateTest
```

Expected: PASS.

- [ ] **Step 6: Commit Task 1**

```bash
git add .env.example .env.production.example tests/Feature/ProductionEnvironmentTemplateTest.php
git commit -m "chore: add cpanel production env template"
```

---

### Task 2: cPanel Deployment Documentation and README

**Files:**
- Create: `docs/deployment/CPANEL-PRODUCTION.md`
- Modify: `README.md`
- Create: `tests/Feature/DeploymentDocumentationTest.php`

**Interfaces:**
- Consumes: production structure from the Phase 6 spec and `.env.production.example` from Task 1.
- Produces: operator-facing cPanel deployment guide and SIRIKA README.

- [ ] **Step 1: Write failing deployment documentation test**

Create `tests/Feature/DeploymentDocumentationTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentDocumentationTest extends TestCase
{
    /** @test */
    public function cpanel_deployment_guide_documents_sirika_production_structure()
    {
        $path = base_path('docs/deployment/CPANEL-PRODUCTION.md');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('sirika.vdnisite.com', $contents);
        $this->assertStringContainsString('public_html/prod-sirika', $contents);
        $this->assertStringContainsString('source Laravel lengkap berada di luar public_html', $contents);
        $this->assertStringContainsString('composer install --no-dev --optimize-autoloader', $contents);
        $this->assertStringContainsString('php artisan config:cache', $contents);
        $this->assertStringContainsString('php artisan route:cache', $contents);
        $this->assertStringContainsString('php artisan view:cache', $contents);
        $this->assertStringContainsString('Backup database', $contents);
        $this->assertStringContainsString('Rollback', $contents);
        $this->assertStringContainsString('composer audit', $contents);
        $this->assertStringContainsString('Laravel 8', $contents);
    }

    /** @test */
    public function readme_documents_sirika_instead_of_default_laravel_copy()
    {
        $contents = file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('# SIRIKA', $contents);
        $this->assertStringContainsString('Sistem Rute Izin Kendaraan', $contents);
        $this->assertStringContainsString('sirika.vdnisite.com', $contents);
        $this->assertStringNotContainsString('Laravel is a web application framework', $contents);
        $this->assertStringNotContainsString('Laravel Sponsors', $contents);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
php artisan test --filter=DeploymentDocumentationTest
```

Expected: FAIL because deployment guide does not exist and README still contains default Laravel copy.

- [ ] **Step 3: Create cPanel deployment guide**

Create `docs/deployment/CPANEL-PRODUCTION.md`:

````markdown
# SIRIKA cPanel Production Deployment

Panduan ini untuk deployment SIRIKA di shared hosting cPanel.

Production URL: `https://sirika.vdnisite.com`
Public web root: `public_html/prod-sirika`
Source Laravel: di luar `public_html`

## Struktur Folder

Struktur yang digunakan:

```text
/home/CPANEL_USER/
  sirika-app/
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    storage/
    vendor/
    .env
    artisan
    composer.json
    composer.lock

  public_html/
    prod-sirika/
      index.php
      css/
      js/
      images/
      mix-manifest.json
      favicon.ico
      .htaccess
```

`public_html/prod-sirika` hanya boleh berisi isi folder Laravel `public/`. Source Laravel lengkap berada di luar public_html.

Folder/file berikut tidak boleh berada di `public_html/prod-sirika`:

- `.env`
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `resources/`
- `routes/`
- `storage/`
- `tests/`
- `vendor/`
- `composer.json`
- `composer.lock`

## Persiapan Deploy Awal

1. Backup database jika sudah ada data.
2. Upload source Laravel lengkap ke folder di luar `public_html`, misalnya `/home/CPANEL_USER/sirika-app`.
3. Upload isi folder `public/` Laravel ke `public_html/prod-sirika`.
4. Buat `.env` production dari `.env.production.example`.
5. Isi credential database cPanel di `.env`.
6. Pastikan `APP_DEBUG=false`.
7. Pastikan `APP_URL=https://sirika.vdnisite.com`.
8. Pastikan `SESSION_SECURE_COOKIE=true`.

## Menyesuaikan public index.php

File `public_html/prod-sirika/index.php` harus menunjuk ke source Laravel di luar `public_html`.

Contoh jika source berada di `/home/CPANEL_USER/sirika-app`:

```php
require __DIR__.'/../../sirika-app/vendor/autoload.php';

$app = require_once __DIR__.'/../../sirika-app/bootstrap/app.php';
```

Cek path aktual lewat Terminal cPanel:

```bash
pwd
ls -la
```

Jangan menebak path. Path salah akan menyebabkan error 500.

## Command Deploy Awal

Jalankan dari folder source Laravel, bukan dari `public_html/prod-sirika`:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Generate APP_KEY hanya untuk environment baru:

```bash
php artisan key:generate --show
```

Copy hasilnya ke `APP_KEY=` di `.env`. Jangan menjalankan `php artisan key:generate` ulang pada production yang sudah berjalan kecuali sedang membuat environment baru.

Jalankan migration hanya jika database belum dibuat atau ada migration baru:

```bash
php artisan migrate --force
```

Jalankan seeder hanya saat dibutuhkan dan setelah `SIRIKA_SEED_USER_PASSWORD` diisi:

```bash
php artisan db:seed --force
```

## Deploy Update

1. Backup database.
2. Upload source baru ke folder source Laravel.
3. Upload asset baru dari folder `public/` ke `public_html/prod-sirika`.
4. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika tidak ada migration baru, `php artisan migrate --force` tetap aman secara Laravel, tetapi operator harus membaca daftar migration sebelum deploy besar.

## Permission

Rekomendasi umum shared hosting:

- Folder umum: `755`
- File umum: `644`
- `storage/` harus writable oleh user hosting.
- `bootstrap/cache/` harus writable oleh user hosting.
- `.env` tidak boleh berada di public web root.

Jika permission berbeda karena aturan hosting, ikuti prinsip: public hanya membaca file public, Laravel hanya menulis ke `storage/` dan `bootstrap/cache/`.

## Smoke Test Setelah Deploy

1. Buka `https://sirika.vdnisite.com/login`.
2. Login sebagai Super Admin atau Admin HR.
3. Buka dashboard.
4. Buka daftar izin.
5. Buka laporan izin dan export Excel.
6. Buka laporan scan dan export Excel.
7. Login sebagai Security.
8. Scan QR valid dan QR invalid.
9. Pastikan Security tidak bisa membuka `/reports/permits` dan `/reports/scans`.

## Rollback

Rollback aman membutuhkan backup.

1. Simpan copy release source dan public asset sebelum deploy update.
2. Backup database sebelum menjalankan migration.
3. Jika deploy gagal sebelum migration, restore source dan asset release sebelumnya.
4. Jalankan cache ulang:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika migration baru sudah berjalan, jangan rollback database tanpa backup dan evaluasi manual.

## Audit Dependency

`npm audit --omit=dev` terakhir bersih untuk dependency production Node.

`composer audit` masih melaporkan advisory baseline pada Laravel 8 dan package bawaan Laravel 8 seperti `swiftmailer/swiftmailer`. Ini adalah risiko residual yang tidak diselesaikan di Phase 6 karena membutuhkan phase upgrade Laravel/PHP terpisah.
````

- [ ] **Step 4: Replace README with SIRIKA project readme**

Replace `README.md` with:

```markdown
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
```

- [ ] **Step 5: Run documentation tests**

Run:

```bash
php artisan test --filter=DeploymentDocumentationTest
```

Expected: PASS.

- [ ] **Step 6: Commit Task 2**

Because `docs/` is ignored for new files in this repository, force-add the deployment guide.

```bash
git add README.md tests/Feature/DeploymentDocumentationTest.php
git add -f docs/deployment/CPANEL-PRODUCTION.md
git commit -m "docs: add cpanel production deployment guide"
```

---

### Task 3: Host, Session, and CORS Hardening

**Files:**
- Modify: `app/Http/Kernel.php`
- Modify: `app/Http/Middleware/TrustHosts.php`
- Modify: `config/sirika.php`
- Modify: `config/session.php`
- Modify: `config/cors.php`
- Create: `tests/Feature/ProductionHardeningConfigTest.php`

**Interfaces:**
- Consumes: `SIRIKA_TRUSTED_HOSTS`, `SESSION_SAME_SITE`, and CORS env keys from Task 1.
- Produces: production-safe host/session/CORS defaults used by cache commands and cPanel deployment.

- [ ] **Step 1: Write failing production hardening config test**

Create `tests/Feature/ProductionHardeningConfigTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Kernel;
use App\Http\Middleware\TrustHosts;
use ReflectionClass;
use Tests\TestCase;

class ProductionHardeningConfigTest extends TestCase
{
    /** @test */
    public function trust_hosts_middleware_is_enabled_globally()
    {
        $kernel = app(Kernel::class);
        $reflection = new ReflectionClass($kernel);
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);

        $this->assertContains(TrustHosts::class, $property->getValue($kernel));
    }

    /** @test */
    public function trust_hosts_are_loaded_from_sirika_config()
    {
        config(['sirika.trusted_hosts' => ['sirika.vdnisite.com', 'www.sirika.vdnisite.com']]);

        $hosts = (new TrustHosts())->hosts();

        $this->assertContains('^sirika\.vdnisite\.com$', $hosts);
        $this->assertContains('^www\.sirika\.vdnisite\.com$', $hosts);
    }

    /** @test */
    public function session_same_site_is_configurable_for_production()
    {
        $config = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $config);
        $this->assertSame('lax', config('session.same_site'));
    }

    /** @test */
    public function cors_defaults_are_restricted_to_configured_paths_and_origins()
    {
        $this->assertSame([], config('cors.paths'));
        $this->assertSame(['https://sirika.vdnisite.com'], config('cors.allowed_origins'));
        $this->assertSame(['GET', 'POST', 'OPTIONS'], config('cors.allowed_methods'));
        $this->assertFalse(config('cors.supports_credentials'));
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
php artisan test --filter=ProductionHardeningConfigTest
```

Expected: FAIL because `TrustHosts` is commented out, trusted hosts are not configured, session same-site is hardcoded, and CORS defaults are broad.

- [ ] **Step 3: Enable TrustHosts globally**

In `app/Http/Kernel.php`, change the global middleware array from:

```php
protected $middleware = [
    // \App\Http\Middleware\TrustHosts::class,
    \App\Http\Middleware\TrustProxies::class,
```

to:

```php
protected $middleware = [
    \App\Http\Middleware\TrustHosts::class,
    \App\Http\Middleware\TrustProxies::class,
```

- [ ] **Step 4: Add trusted host config**

Replace `config/sirika.php` with:

```php
<?php

return [
    'seed_user_password' => env('SIRIKA_SEED_USER_PASSWORD'),

    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', env(
        'SIRIKA_TRUSTED_HOSTS',
        'sirika.vdnisite.com'
    ))))),

    'route_map' => [
        'key' => env('SIRIKA_ROUTE_MAP_KEY', 'vdni-road-map-v1'),
        'image_url' => env('SIRIKA_ROUTE_MAP_IMAGE_URL', '/images/maps/vdni-road-map-v1.png'),
        'width' => (int) env('SIRIKA_ROUTE_MAP_WIDTH', 3370),
        'height' => (int) env('SIRIKA_ROUTE_MAP_HEIGHT', 2384),
    ],
];
```

- [ ] **Step 5: Update TrustHosts middleware**

Replace `app/Http/Middleware/TrustHosts.php` with:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        $configuredHosts = config('sirika.trusted_hosts', []);

        if (! is_array($configuredHosts)) {
            $configuredHosts = [];
        }

        $hosts = array_values(array_filter(array_map(function ($host) {
            $host = trim((string) $host);

            if ($host === '') {
                return null;
            }

            return '^' . preg_quote($host, '/') . '$';
        }, $configuredHosts)));

        if ($hosts !== []) {
            return $hosts;
        }

        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
```

- [ ] **Step 6: Make session same-site configurable**

In `config/session.php`, replace:

```php
'same_site' => 'lax',
```

with:

```php
'same_site' => env('SESSION_SAME_SITE', 'lax'),
```

- [ ] **Step 7: Restrict default CORS config**

Replace `config/cors.php` with:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | SIRIKA is currently a session-based web application. Production CORS is
    | intentionally conservative and can be opened with env values when a real
    | API consumer is introduced.
    |
    */

    'paths' => array_values(array_filter(array_map('trim', explode(',', env('CORS_PATHS', ''))))),

    'allowed_methods' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_METHODS',
        'GET,POST,OPTIONS'
    ))))),

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'https://sirika.vdnisite.com'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_HEADERS',
        'Content-Type,X-Requested-With,X-CSRF-TOKEN,Authorization'
    ))))),

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
```

- [ ] **Step 8: Run hardening config tests**

Run:

```bash
php artisan test --filter=ProductionHardeningConfigTest
```

Expected: PASS.

- [ ] **Step 9: Run auth and web smoke tests**

Run:

```bash
php artisan test --filter=AuthAndRoleAccessTest
php artisan test --filter=UserManagementHttpTest
```

Expected: PASS. This verifies host middleware did not break local testing.

- [ ] **Step 10: Commit Task 3**

```bash
git add app/Http/Kernel.php app/Http/Middleware/TrustHosts.php config/sirika.php config/session.php config/cors.php tests/Feature/ProductionHardeningConfigTest.php
git commit -m "chore: harden production host session and cors config"
```

---

### Task 4: Route and Cache Readiness

**Files:**
- Create: `app/Http/Controllers/HomeController.php`
- Create: `app/Http/Controllers/Api/AuthenticatedUserController.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/ProductionCacheCommandTest.php`

**Interfaces:**
- Consumes: existing root redirect behavior and default authenticated API user route.
- Produces: cacheable routes and test coverage for `config:cache`, `route:cache`, and `view:cache`.

- [ ] **Step 1: Write failing cache command test**

Create `tests/Feature/ProductionCacheCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        parent::tearDown();
    }

    /** @test */
    public function production_cache_commands_complete_successfully()
    {
        $this->assertSame(0, Artisan::call('config:clear'));
        $this->assertSame(0, Artisan::call('route:clear'));
        $this->assertSame(0, Artisan::call('view:clear'));

        $this->assertSame(0, Artisan::call('config:cache'));
        $this->assertSame(0, Artisan::call('route:cache'));
        $this->assertSame(0, Artisan::call('view:cache'));
    }

    /** @test */
    public function root_redirect_behavior_stays_the_same_after_replacing_route_closure()
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
```

- [ ] **Step 2: Run the test and verify route cache fails**

Run:

```bash
php artisan test --filter=ProductionCacheCommandTest
```

Expected: FAIL on `route:cache` because `routes/web.php` and `routes/api.php` still contain closure routes.

- [ ] **Step 3: Add HomeController**

Create `app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function __invoke()
    {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
```

- [ ] **Step 4: Add authenticated API user controller**

Create `app/Http/Controllers/Api/AuthenticatedUserController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthenticatedUserController extends Controller
{
    public function __invoke(Request $request)
    {
        return $request->user();
    }
}
```

- [ ] **Step 5: Update web root route**

In `routes/web.php`, add import:

```php
use App\Http\Controllers\HomeController;
```

Replace:

```php
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});
```

with:

```php
Route::get('/', HomeController::class);
```

- [ ] **Step 6: Update API user route**

Replace `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Api\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| SIRIKA is currently a session-based web application. This authenticated
| user endpoint remains controller-based so production route caching works.
|
*/

Route::middleware('auth:sanctum')->get('/user', AuthenticatedUserController::class);
```

- [ ] **Step 7: Run cache command test**

Run:

```bash
php artisan test --filter=ProductionCacheCommandTest
```

Expected: PASS.

- [ ] **Step 8: Run route/auth regression tests**

Run:

```bash
php artisan test --filter=AuthAndRoleAccessTest
php artisan test --filter=ReportAuthorizationTest
```

Expected: PASS.

- [ ] **Step 9: Ensure local cache files are cleared**

Run:

```bash
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

Expected: each command exits successfully.

- [ ] **Step 10: Commit Task 4**

```bash
git add app/Http/Controllers/HomeController.php app/Http/Controllers/Api/AuthenticatedUserController.php routes/web.php routes/api.php tests/Feature/ProductionCacheCommandTest.php
git commit -m "chore: make production routes cacheable"
```

---

### Task 5: Final Phase 6 Verification and Production Notes

**Files:**
- Modify only if verification exposes stale docs or test assertions:
  - `docs/deployment/CPANEL-PRODUCTION.md`
  - `README.md`
  - `.env.production.example`
  - Tests created in Tasks 1-4

**Interfaces:**
- Consumes: all Phase 6 hardening changes.
- Produces: verified production-readiness state and final risk notes.

- [ ] **Step 1: Run focused Phase 6 tests**

Run:

```bash
php artisan test --filter=ProductionEnvironmentTemplateTest
php artisan test --filter=DeploymentDocumentationTest
php artisan test --filter=ProductionHardeningConfigTest
php artisan test --filter=ProductionCacheCommandTest
```

Expected: PASS for all.

- [ ] **Step 2: Run critical workflow regressions**

Run:

```bash
php artisan test --filter=AuthAndRoleAccessTest
php artisan test --filter=Import
php artisan test --filter=PermitReview
php artisan test --filter=PermitQr
php artisan test --filter=ScanQr
php artisan test --filter=Report
php artisan test --filter=PermitRouteMap
```

Expected: PASS. Do not change domain rules to satisfy hardening tests.

- [ ] **Step 3: Run full test suite**

Run:

```bash
php artisan test
```

Expected: PASS.

- [ ] **Step 4: Run production cache commands manually**

Run:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Expected: all commands exit successfully. The final clear commands keep local development state clean.

- [ ] **Step 5: Run dependency audits**

Run:

```bash
npm audit --omit=dev
composer audit
```

Expected:

- `npm audit --omit=dev` reports `found 0 vulnerabilities`.
- `composer audit` may report Laravel 8 baseline advisories and abandoned packages. Record exact summary in final output. Do not claim Composer audit is clean unless it actually returns exit code 0.

- [ ] **Step 6: Check git status**

Run:

```bash
git status --short --branch
```

Expected: clean `main` or clean Phase 6 feature branch after final commit.

- [ ] **Step 7: Commit verification fixes if needed**

If Step 1-5 required small doc/test corrections:

```bash
git add .env.production.example .env.example README.md docs/deployment/CPANEL-PRODUCTION.md tests/Feature/ProductionEnvironmentTemplateTest.php tests/Feature/DeploymentDocumentationTest.php tests/Feature/ProductionHardeningConfigTest.php tests/Feature/ProductionCacheCommandTest.php
git commit -m "test: cover cpanel production readiness"
```

If no files changed, do not create an empty commit.

---

## Self-Review Checklist

- Spec coverage:
  - `.env.production.example`: Task 1.
  - cPanel deployment guide: Task 2.
  - README SIRIKA: Task 2.
  - TrustHosts: Task 3.
  - Session secure/same-site config: Task 1 and Task 3.
  - CORS hardening: Task 3.
  - Route/config/view cache readiness: Task 4 and Task 5.
  - Composer audit residual risk: Task 2 and Task 5.
  - No Laravel/PHP upgrade: Global Constraints and no task changes dependencies.

- Type consistency:
  - `HomeController::__invoke()` is referenced by `routes/web.php`.
  - `AuthenticatedUserController::__invoke()` is referenced by `routes/api.php`.
  - `SIRIKA_TRUSTED_HOSTS` maps to `config('sirika.trusted_hosts')`.
  - `SESSION_SAME_SITE` maps to `config('session.same_site')`.
  - CORS env keys map to `config/cors.php`.

- Production safety:
  - No secrets are committed.
  - No migration is introduced.
  - No domain workflow behavior is changed.
  - Source/public split is documented.
  - Cache commands are verified and cleared after local tests.

Plan complete and saved to `docs/superpowers/plans/2026-07-10-sirika-phase-6-cpanel-production-hardening.md`.

Two execution options:

1. Subagent-Driven (recommended) - dispatch a fresh subagent per task, review between tasks, fast iteration.
2. Inline Execution - execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
