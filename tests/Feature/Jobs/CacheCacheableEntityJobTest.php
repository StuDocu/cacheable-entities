<?php

namespace StuDocu\CacheableEntities\Tests\Feature\Jobs;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Str;
use Mockery;
use StuDocu\CacheableEntities\Jobs\CacheCacheableEntityJob;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\CacheableEntityStub;
use StuDocu\CacheableEntities\Tests\Feature\Stubs\SerializableCacheableEntityStub;
use StuDocu\CacheableEntities\Tests\TestCase;

class CacheCacheableEntityJobTest extends TestCase
{
    /** @test */
    public function it_caches_the_values(): void
    {
        // Arrange

        $expectedValue = $this->generateRandomExpectedValue();

        $dummyEntity = new CacheableEntityStub($expectedValue);

        $this->assertFalse(
            cache()->has($dummyEntity->getCacheKey()),
            'Pre-condition failed.',
        );

        // Act

        (new CacheCacheableEntityJob($dummyEntity))
            ->handle(resolve(CacheRepository::class));

        // Assert

        $this->assertTrue(
            cache()->has($dummyEntity->getCacheKey()),
            'The value is not cached.',
        );

        $this->assertEquals(
            $expectedValue,
            cache()->get($dummyEntity->getCacheKey()),
            'The cached value is invalid.',
        );
    }

    /** @test */
    public function it_serializes_values(): void
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

        (new CacheCacheableEntityJob($dummyEntity))
            ->handle(resolve(CacheRepository::class));

        // Assert

        $this->assertTrue(
            cache()->has($dummyEntity->getCacheKey()),
        );

        $this->assertEquals(
            $dummyEntity->serialize($expectedValue),
            cache()->get($dummyEntity->getCacheKey()),
        );
    }

    /** @test */
    public function it_sets_the_ttl_correctly(): void
    {
        // Arrange

        $dummyEntity = new CacheableEntityStub(fake()->text);

        $cacheSpy = Mockery::spy(CacheRepository::class);

        // Act

        (new CacheCacheableEntityJob($dummyEntity))
            ->handle($cacheSpy);

        // Assert

        $cacheSpy->shouldHaveReceived('put', function (...$args) use ($dummyEntity) {
            return $args[0] === $dummyEntity->getCacheKey()
                && $args[2] === $dummyEntity->getCacheTTL()
                && value($args[1]) === $dummyEntity->get();
        });
    }

    /** @test */
    public function it_dispatches_unique_jobs(): void
    {
        // Arrange

        $dummyEntity = new CacheableEntityStub(fake()->text);

        $job = new CacheCacheableEntityJob($dummyEntity);

        // Assert

        $this->assertInstanceOf(
            ShouldBeUnique::class,
            $job,
        );

        $this->assertEquals(
            $dummyEntity->getCacheKey(),
            $job->uniqueId(),
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
