<?php

namespace StuDocu\CacheableEntities\Contracts;

/**
 * @template TDefault
 */
interface SupportsDefaultValue
{
    /**
     * @return TDefault
     */
    public function getCacheMissValue(): mixed;
}
