<?php

namespace StuDocu\CacheableEntities\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use StuDocu\CacheableEntities\AsyncCache;
use StuDocu\CacheableEntities\Contracts\SupportsDefaultValue;
use StuDocu\CacheableEntities\Jobs\CacheCacheableEntityJob;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\CacheableEntityStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\SerializableCacheableEntityStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\SerializableCacheableEntityWithInvalidCacheValueStub;
use StuDocu\CacheableEntities\Tests\TestCase;

class AsyncCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function it_gets_already_cached_value(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new CacheableEntityStub(fake()->unique()->text);

        cache()->forever($dummyEntity->getCacheKey(), $expectedValue);

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCachedAsync()
            : resolve(AsyncCache::class)->get($dummyEntity);

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
    public function test_it_dispatches_a_job_to_compute_missing_cache_value(bool $isSelfCacheable): void
    {
        // Arrange

        $cacheableValue = $this->generateRandomExpectedValue();

        $dummyEntity = new CacheableEntityStub($cacheableValue);

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCachedAsync()
            : resolve(AsyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertNull(
            $value,
        );

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
        );

        Queue::assertPushed(
            CacheCacheableEntityJob::class,
            fn (CacheCacheableEntityJob $job) => $job->cacheable === $dummyEntity,
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function test_it_falls_back_to_cache_missed_value(bool $isSelfCacheable): void
    {
        // Arrange

        $cacheableValue = $this->generateRandomExpectedValue();

        $cacheMissValue = fake()->randomElement([
            null,
            [],
            0,
            (object) [],
        ]);

        $dummyEntity = new class($cacheableValue, $cacheMissValue) extends CacheableEntityStub implements SupportsDefaultValue
        {
            public function __construct(
                mixed $value,
                private readonly mixed $defaultValue
            ) {
                parent::__construct($value);
            }

            public function getCacheMissValue(): mixed
            {
                return $this->defaultValue;
            }
        };

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCachedAsync()
            : resolve(AsyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertEquals(
            $cacheMissValue,
            $value,
        );

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
        );

        Queue::assertPushed(
            CacheCacheableEntityJob::class,
            fn (CacheCacheableEntityJob $job) => $job->cacheable === $dummyEntity,
        );
    }

    /**
     * @test
     *
     * @testWith [false]
     *           [true]
     */
    public function test_it_unserializes_cacheable_entity(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityStub(
            $expectedValue,
        );

        cache()->forever($dummyEntity->getCacheKey(), $dummyEntity->serialize($expectedValue));

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCachedAsync()
            : resolve(AsyncCache::class)->get($dummyEntity);

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
    public function test_it_recomputes_cache_value_if_serialized_value_is_invalid(bool $isSelfCacheable): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new SerializableCacheableEntityWithInvalidCacheValueStub(
            $expectedValue,
        );

        cache()->forever($dummyEntity->getCacheKey(), '::serialized_value::');

        // Act

        $value = $isSelfCacheable
            ? $dummyEntity->getCachedAsync()
            : resolve(AsyncCache::class)->get($dummyEntity);

        // Assert

        $this->assertNull(
            $value,
        );

        Queue::assertPushed(
            CacheCacheableEntityJob::class,
            fn (CacheCacheableEntityJob $job) => $job->cacheable === $dummyEntity,
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
