<?php

namespace Queries;

use Author;
use Book;
use Illuminate\Database\Eloquent\Collection;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SupportsDefaultValue;

/**
 * @phpstan-type ReturnStructure Collection<int, Book>
 *
 * @implements Cacheable<ReturnStructure>
 * @implements SupportsDefaultValue<ReturnStructure>
 */
class AuthorPopularBooksWithStaleCacheQuery implements Cacheable, SupportsDefaultValue
{
    public const DEFAULT_LIMIT = 8;

    public function __construct(
        protected readonly Author $author,
        protected readonly int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    public function getCacheTTL(): int
    {
        return 3600 * 24;
    }

    public function getCacheKey(): string
    {
        return "authors:{$this->author->id}:books:popular.v1";
    }

    public function get(): Collection
    {
        $books = Book::query()
            ->join('book_popularity_scores', 'book_popularity_scores.book_id', '=', 'books.id')
            ->where('author_id', $this->author->id)
            ->whereValid()
            ->whereHas('ratings')
            ->orderByDesc('document_popularity_scores.score')
            ->take($this->limit)
            ->get();

        $this->setRelations($books);

        return tap(
            $books,
            function (Collection $results) {
                cache()->put(
                    $this->getStaleCacheKey(),
                    $results,
                    $this->getStaleCacheTTL(),
                );
            },
        );
    }

    private function getStaleCacheKey(): string
    {
        return $this->getCacheKey().':stale';
    }

    private function getStaleCacheTTL(): int
    {
        return $this->getCacheTTL() + (3600 * 24);
    }

    /**
     * @param  ReturnStructure  $books
     */
    private function setRelations(Collection $books): void
    {
        $books->each->setRelation('author', $this->author);

        // Generally speaking, you can do eager loading and such in a similar fashion (for ::get and ::unserialzie).
    }

    public function getCacheMissValue(): Collection
    {
        $books = cache()->get($this->getStaleCacheKey(), Collection::empty());

        if (! ($books instanceof Collection) || $books->isEmpty()) {
            // When we neither have the up-to-date results nor the stale results cached, we compute
            // them synchronously as a last resort.
            return $this->get();

            // Or you can return an empty collection if you don't want to have a value every
        }

        return $books;
    }
}
