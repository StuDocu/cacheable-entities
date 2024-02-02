<?php

namespace StuDocu\CacheableEntities\Concerns;

use StuDocu\CacheableEntities\AsyncCache;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\SyncCache;

/**
 * @template TReturn
 *
 * @mixin Cacheable<TReturn>
 */
trait SelfCacheable
{
    /**
     * @return TReturn
     *
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getCached(): mixed
    {
        return resolve(SyncCache::class)->get($this);
    }

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function forgetCache(): void
    {
        resolve(SyncCache::class)->forget($this);
    }

    /**
     * @return TReturn|null
     *
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getCachedAsync(): mixed
    {
        return resolve(AsyncCache::class)->get($this);
    }
}
