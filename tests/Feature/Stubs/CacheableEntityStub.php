<?php

namespace StuDocu\CacheableEntities\Tests\Feature\Stubs;

use StuDocu\CacheableEntities\Concerns\SelfCacheable;
use StuDocu\CacheableEntities\Contracts\Cacheable;

class CacheableEntityStub implements Cacheable
{
    use SelfCacheable;

    protected int $ttl;

    protected string $key;

    public function __construct(
        protected readonly mixed $value,
    ) {
        $this->ttl = rand(60, 7200);
        $this->key = fake()->lexify('????????');
    }

    public function getCacheTTL(): int
    {
        return $this->ttl;
    }

    public function getCacheKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
