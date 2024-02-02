<?php

namespace StuDocu\CacheableEntities\Tests\Feature\Stubs;

use StuDocu\CacheableEntities\Exceptions\CorruptSerializedCacheValueException;

class SerializableCacheableEntityWithInvalidCacheValueStub extends SerializableCacheableEntityStub
{
    /**
     * @throws CorruptSerializedCacheValueException
     */
    public function unserialize(mixed $value): mixed
    {
        throw new CorruptSerializedCacheValueException();
    }
}
