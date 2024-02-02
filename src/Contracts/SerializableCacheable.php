<?php

namespace StuDocu\CacheableEntities\Contracts;

use StuDocu\CacheableEntities\Exceptions\CorruptSerializedCacheValueException;

/**
 * @template TUnserialized
 * @template TSerialized
 */
interface SerializableCacheable
{
    /**
     * @param  TUnserialized  $value
     * @return TSerialized
     */
    public function serialize(mixed $value): mixed;

    /**
     * @param  TSerialized  $value
     * @return TUnserialized
     *
     * @throws CorruptSerializedCacheValueException
     */
    public function unserialize(mixed $value): mixed;
}
