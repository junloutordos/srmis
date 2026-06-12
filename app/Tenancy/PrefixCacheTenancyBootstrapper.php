<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Isolates each tenant's cache by swapping the cache key prefix instead of
 * relying on cache tags (the stock CacheTenancyBootstrapper requires a
 * taggable store, which rules out the database/file stores used in dev).
 */
class PrefixCacheTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?string $originalPrefix = null;

    public function __construct(protected Application $app)
    {
    }

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalPrefix = $this->app['config']['cache.prefix'];

        $this->setPrefix($this->originalPrefix . '_tenant_' . $tenant->getTenantKey());
    }

    public function revert(): void
    {
        if ($this->originalPrefix !== null) {
            $this->setPrefix($this->originalPrefix);
            $this->originalPrefix = null;
        }
    }

    protected function setPrefix(string $prefix): void
    {
        $this->app['config']['cache.prefix'] = $prefix;

        // Forget resolved stores so the next cache() call picks up the prefix.
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->app->instance('cache', new CacheManager($this->app));
    }
}
