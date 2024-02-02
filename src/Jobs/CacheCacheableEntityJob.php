<?php

namespace StuDocu\CacheableEntities\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;

/** @template T */
class CacheCacheableEntityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  Cacheable<T>  $cacheable
     */
    public function __construct(
        public readonly Cacheable $cacheable,
    ) {
    }

    public function handle(CacheRepository $cache): void
    {
        $value = $this->cacheable instanceof SerializableCacheable
            ? $this->cacheable->serialize($this->cacheable->get())
            : $this->cacheable->get();

        $cache->put(
            key: $this->cacheable->getCacheKey(),
            value: $value,
            ttl: $this->cacheable->getCacheTTL(),
        );
    }

    public function uniqueId(): string
    {
        return $this->cacheable->getCacheKey();
    }
}
