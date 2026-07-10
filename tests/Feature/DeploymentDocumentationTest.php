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
        $this->assertStringContainsString('jangan meng-upload atau menimpa `index.php`', $contents);
        $this->assertStringContainsString("__DIR__.'/../../sirika-app/storage/framework/maintenance.php'", $contents);
        $this->assertStringContainsString('cPanel Domains/Subdomains', $contents);
        $this->assertStringContainsString('Document Root `public_html/prod-sirika`', $contents);
        $this->assertStringContainsString('verifikasi mapping domain', $contents);
        $this->assertStringContainsString('ubah hanya tiga ekspresi path', $contents);
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
