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
