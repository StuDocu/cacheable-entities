<?php

namespace StuDocu\CacheableEntities\Tests\Feature;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use StuDocu\CacheableEntities\SyncCache;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\CacheableEntityStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\CacheableEntityWithDynamicTTLStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\SerializableCacheableEntityStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\SerializableCacheableEntityWithInvalidCacheValueStub;
use StuDocu\CacheableEntities\Tests\TestCase;

class SyncCacheTest extends TestCase
{
    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_gets_cached_value(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new CacheableEntityStub(fake()->unique()->text);

        cache()->forever($dummyEntity->getCacheKey(), $expectedValue);

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertSame(
            $expectedValue,
            $value,
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_computes_cache_value_when_missing(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new CacheableEntityStub($expectedValue);

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertSame(
            $expectedValue,
            $value,
        );

        $this->assertTrue(
            cache()->has($dummyEntity->getCacheKey()),
        );

        $this->assertEquals(
            $expectedValue,
            cache()->get($dummyEntity->getCacheKey()),
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_forgets_cache(bool $isSelfCacheable): void
    {
        // Arrange

        $dummyEntity = new CacheableEntityStub(fake()->text);

        cache()->forever($dummyEntity->getCacheKey(), $dummyEntity->get());

        // Act

        $isSelfCacheable
            ? $dummyEntity->forgetCache()
            : resolve(SyncCache::class)->forget($dummyEntity);

        // Assert

        $this->assertFalse(cache()->has($dummyEntity->getCacheKey()));
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_sets_the_ttl_properly(bool $isSelfCacheable): void
    {
        // Arrange

        $dummyEntity = new CacheableEntityStub(fake()->text);

        $cacheSpy = $this->spy(Repository::class);

        // Act

        $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $cacheSpy->shouldHaveReceived('remember', function (...$args) use ($dummyEntity) {
            return $args[0] === $dummyEntity->getCacheKey()
                && value($args[1]) === $dummyEntity->getCacheTTL()
                && value($args[2]) === $dummyEntity->get();
        });
    }

    /**
     * @test
     *
     * @testWith [false, 1]
     *           [true, 1]
     *           [false, 2]
     *           [true, 2]
     */
    public function it_sets_cache_ttl_based_on_results(bool $isSelfCacheable, int $value): void
    {
        // Arrange

        $dummyEntity = new CacheableEntityWithDynamicTTLStub($value);

        $cacheSpy = $this->spy(Repository::class);

        // Act

        $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $cacheSpy->shouldHaveReceived('remember', function (...$args) use ($dummyEntity) {
            return $args[0] === $dummyEntity->getCacheKey()
                && value($args[2]) === $dummyEntity->get() // <- it has to be in this order to assert dynamic TTL.
                && value($args[1]) === $dummyEntity->getCacheTTL();
        });
    }

    /**
     * @testWith [false]
     *           [true]
     */
    public function it_serializes_cacheable_entity(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityStub(
            $expectedValue,
        );

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertSame(
            $expectedValue,
            $value,
        );

        $this->assertTrue(
            cache()->has($dummyEntity->getCacheKey()),
        );

        $this->assertEquals(
            $dummyEntity->serialize($expectedValue),
            cache()->get($dummyEntity->getCacheKey()),
        );
    }

    /**
     * @testWith [false]
     *           [true]
     */
    public function it_unserializes_cacheable_entity(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityStub(
            $expectedValue,
        );

        cache()->forever($dummyEntity->getCacheKey(), $dummyEntity->serialize($expectedValue));

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertEquals(
            $expectedValue,
            $value,
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_computes_cache_value_when_serializable_is_missing(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityStub($expectedValue);

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertSame(
            $expectedValue,
            $value,
        );

        $this->assertTrue(
            cache()->has($dummyEntity->getCacheKey()),
        );

        $this->assertEquals(
            $expectedValue,
            unserialize(cache()->get($dummyEntity->getCacheKey())),
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_recomputes_cache_value_if_serialized_value_is_invalid(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityWithInvalidCacheValueStub(
            $expectedValue,
        );

        cache()->forever($dummyEntity->getCacheKey(), '::serialized_value::');

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCached()
            : resolve(SyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertEquals(
            $expectedValue,
            $value,
        );

        $this->assertEquals(
            $dummyEntity->serialize($expectedValue),
            cache()->get($dummyEntity->getCacheKey()),
            'The old broken cache was not invalidated.',
        );
    }

    /*
     * Generators.
     */

    private function generateRandomExpectedValue(): mixed
    {
        return fake()
            ->randomElement([
                Str::random(),
                fake()->randomNumber(),
                [1, 2, 3],
                new \stdClass(),
            ]);
    }
}
