<?php

namespace Queries;

use Book;
use Illuminate\Database\Eloquent\Collection;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;
use StuDocu\CacheableEntities\Exceptions\CorruptSerializedCacheValueException;
use User;

/**
 * @phpstan-type ReturnStructure Collection<int, Book>
 * @phpstan-type SerializedStructure array<int>
 *
 * @implements Cacheable<ReturnStructure>
 * @implements SerializableCacheable<ReturnStructure, SerializedStructure>
 */
class UserRecommendedBooks implements Cacheable, SerializableCacheable
{
    public const DEFAULT_LIMIT = 8;

    public function __construct(
        protected readonly User $user,
        protected readonly array $followedBookIds = [],
        protected readonly int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    public function getCacheTTL(): int
    {
        return 3600 * 24;
    }

    public function getCacheKey(): string
    {
        return "users:{$this->user->id}:books:recommended.v1";
    }

    public function get(): Collection
    {
        // -> Fetching recommendation logic can go here.
        $books = Collection::empty();

        $this->eagerLoadRelations($books);

        return $books;
    }

    /**
     * @param  Collection<Book>  $value
     * @return array<int>
     */
    public function serialize(mixed $value): array
    {
        return $value->pluck('id')->all();
    }

    /**
     * @return ReturnStructure
     *
     * @throws CorruptSerializedCacheValueException
     */
    public function unserialize(mixed $value, mixed $default = null): Collection
    {
        // Corrupt format cached
        if (! is_array($value)) {
            throw new CorruptSerializedCacheValueException();
        }

        if (empty($value)) {
            return Collection::empty();
        }

        $followedBooksFastAccess = array_flip($this->followedBookIds);

        $value = collect($value)
            // Exclude studylists already followed after being cached.
            ->reject(fn (int $studylistId) => array_key_exists($studylistId, $followedBooksFastAccess));

        // Were all previously computed recommendations followed? Then compute a new value.
        if ($value->isEmpty()) {
            throw new CorruptSerializedCacheValueException();
        }

        $books = Book::query()->findMany($value);

        $this->eagerLoadRelations($books);

        return $books;
    }

    /**
     * @param  ReturnStructure  $books
     */
    private function eagerLoadRelations(Collection $books): void
    {
        $books->loadMissing('author');
    }
}
