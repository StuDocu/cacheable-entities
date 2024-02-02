<?php

namespace StuDocu\CacheableEntities\Tests\Feature\Stubs;

use StuDocu\CacheableEntities\Contracts\SerializableCacheable;

class SerializableCacheableEntityStub extends CacheableEntityStub implements SerializableCacheable
{
    public function __construct(
        mixed $value,
    ) {
        parent::__construct($value);
    }

    public function serialize(mixed $value): mixed
    {
        return serialize($value);
    }

    public function unserialize(mixed $value): mixed
    {
        return unserialize($value);
    }
}
