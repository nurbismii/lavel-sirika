<?php

namespace Tests\Feature;

use App\Http\Kernel;
use App\Http\Middleware\TrustHosts;
use ReflectionClass;
use Tests\TestCase;

class ProductionHardeningConfigTest extends TestCase
{
    public function test_trust_hosts_middleware_is_enabled_globally()
    {
        $kernel = app(Kernel::class);
        $reflection = new ReflectionClass($kernel);
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);

        $this->assertContains(TrustHosts::class, $property->getValue($kernel));
    }

    public function test_trust_hosts_are_loaded_from_sirika_config()
    {
        config(['sirika.trusted_hosts' => ['sirika.vdnisite.com', 'www.sirika.vdnisite.com']]);

        $hosts = app(TrustHosts::class)->hosts();

        $this->assertContains('^sirika\.vdnisite\.com$', $hosts);
        $this->assertContains('^www\.sirika\.vdnisite\.com$', $hosts);
    }

    public function test_trust_hosts_falls_back_to_the_exact_production_host_when_configuration_is_empty()
    {
        config(['sirika.trusted_hosts' => []]);

        $hosts = app(TrustHosts::class)->hosts();

        $this->assertSame(['^sirika\\.vdnisite\\.com$'], $hosts);
    }

    public function test_session_same_site_is_configurable_for_production()
    {
        $config = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $config);
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_cors_defaults_are_restricted_and_environment_overrides_are_respected()
    {
        $configSource = file_get_contents(config_path('cors.php'));
        $configuredOrigins = array_values(array_filter(array_map('trim', explode(',', env(
            'CORS_ALLOWED_ORIGINS',
            'https://sirika.vdnisite.com'
        )))));

        $this->assertSame([], config('cors.paths'));
        $this->assertStringContainsString("'https://sirika.vdnisite.com'", $configSource);
        $this->assertSame($configuredOrigins, config('cors.allowed_origins'));
        $this->assertSame(['GET', 'POST', 'OPTIONS'], config('cors.allowed_methods'));
        $this->assertFalse(config('cors.supports_credentials'));
    }
}
