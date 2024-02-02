<?php

namespace StuDocu\CacheableEntities\Contracts;

/**
 * @template TReturn
 */
interface Cacheable
{
    public function getCacheTTL(): int;

    public function getCacheKey(): string;

    /**
     * @return TReturn
     */
    public function get(): mixed;
}
