<?php

namespace StuDocu\CacheableEntities;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Queue;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;
use StuDocu\CacheableEntities\Contracts\SupportsDefaultValue;
use StuDocu\CacheableEntities\Exceptions\CorruptSerializedCacheValueException;
use StuDocu\CacheableEntities\Jobs\CacheCacheableEntityJob;

/**
 * Non-blocking cache (Asynchronous).
 * If we have the value cached, we return it.
 * Otherwise, we dispatch a job to compute it, then return an empty state.
 * An empty state can be: null, empty collection, empty array, etc.
 */
class AsyncCache
{
    public function __construct(
        private readonly CacheContract $cache,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param  Cacheable<TReturn>  $cacheable
     * @return TReturn|null
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(Cacheable $cacheable): mixed
    {
        if (! $this->cache->has($cacheable->getCacheKey())) {
            return $this->dispatchAsyncJobAndGetDefaultValue($cacheable);
        }

        try {
            $cacheValue = $this->cache->get($cacheable->getCacheKey());

            return $cacheable instanceof SerializableCacheable
                ? $cacheable->unserialize($cacheValue)
                : $cacheValue;
        } catch (CorruptSerializedCacheValueException) {
            return $this->dispatchAsyncJobAndGetDefaultValue($cacheable);
        }
    }

    /**
     * @template TReturn
     *
     * @param  Cacheable<TReturn>  $cacheable
     * @return TReturn|null
     */
    private function dispatchAsyncJobAndGetDefaultValue(Cacheable $cacheable): mixed
    {
        Queue::push(new CacheCacheableEntityJob($cacheable));

        if ($cacheable instanceof SupportsDefaultValue) {
            return $cacheable->getCacheMissValue();
        }

        return null;
    }
}
