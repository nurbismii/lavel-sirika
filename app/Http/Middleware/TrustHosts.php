<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application|null  $app
     * @return void
     */
    public function __construct(Application $app = null)
    {
        if ($app !== null) {
            parent::__construct($app);
        }
    }

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
