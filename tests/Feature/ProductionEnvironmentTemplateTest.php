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
