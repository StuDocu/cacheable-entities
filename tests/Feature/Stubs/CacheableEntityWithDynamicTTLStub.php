<?php

namespace StuDocu\CacheableEntities\Tests\Feature\Stubs;

use Closure;
use StuDocu\CacheableEntities\Concerns\SelfCacheable;
use StuDocu\CacheableEntities\Contracts\Cacheable;

class CacheableEntityWithDynamicTTLStub implements Cacheable
{
    use SelfCacheable;

    protected ?Closure $ttlResolver = null;

    protected int $ttl;

    protected string $key;

    /** @var bool A dummy condition for dynamic TTL. */
    protected bool $isValueAnEvenNumber = false;

    public function __construct(
        protected readonly int $value,
    ) {
        $this->key = fake()->lexify('????????');
    }

    public function resolveTTLUsing(Closure $resolver): self
    {
        $this->ttlResolver = $resolver;

        return $this;
    }

    public function getCacheTTL(): int
    {
        return $this->isValueAnEvenNumber
            ? 3600
            : 3600 * 10;
    }

    public function getCacheKey(): string
    {
        return $this->key;
    }

    public function get(): int
    {
        $this->isValueAnEvenNumber = $this->value % 2 === 0;

        return $this->value;
    }
}
