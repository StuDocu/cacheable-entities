<?php

namespace StuDocu\CacheableEntities;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;
use StuDocu\CacheableEntities\Exceptions\CorruptSerializedCacheValueException;

/**
 * Blocking cache (Synchronous).
 * If we don’t have the value, we compute, cache it, and return the result.
 */
class SyncCache
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param  Cacheable<TReturn>  $cacheable
     * @return TReturn
     */
    public function get(Cacheable $cacheable): mixed
    {
        if ($cacheable instanceof SerializableCacheable) {
            return $this->getSerializableValue($cacheable);
        }

        return $this->cache->remember(
            key: $cacheable->getCacheKey(),
            ttl: fn () => $cacheable->getCacheTTL(),
            callback: fn () => $cacheable->get(),
        );
    }

    /**
     * @template TReturn
     *
     * @param  Cacheable<TReturn>  $cacheable
     */
    public function forget(Cacheable $cacheable): void
    {
        $this->cache->forget($cacheable->getCacheKey());
    }

    /**
     * @template TReturn
     * @template TSerialized
     *
     * @param  Cacheable<TReturn> & SerializableCacheable<TReturn, TSerialized>  $cacheable
     * @return TReturn
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getSerializableValue(Cacheable&SerializableCacheable $cacheable): mixed
    {
        if (! $this->cache->has($cacheable->getCacheKey())) {
            return $this->computeAndCacheSerializableValue($cacheable);
        }

        try {
            return $cacheable->unserialize($this->cache->get($cacheable->getCacheKey()));
        } catch (CorruptSerializedCacheValueException) {
            return $this->computeAndCacheSerializableValue($cacheable);
        }
    }

    /**
     * @template TReturn
     * @template TSerialized
     *
     * @param  SerializableCacheable<TReturn, TSerialized>&Cacheable<TReturn>  $cacheable
     * @return TReturn
     */
    private function computeAndCacheSerializableValue(Cacheable&SerializableCacheable $cacheable): mixed
    {
        $value = $cacheable->get();

        $this->cache->put(
            key: $cacheable->getCacheKey(),
            value: $cacheable->serialize($value),
            ttl: $cacheable->getCacheTTL(),
        );

        return $value;
    }
}
