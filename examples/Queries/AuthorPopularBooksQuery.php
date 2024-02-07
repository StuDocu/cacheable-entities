<?php

namespace Queries;

use Author;
use Book;
use Illuminate\Database\Eloquent\Collection;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;

/**
 * @phpstan-type ReturnStructure Collection<int, Book>
 * @phpstan-type SerializedStructure int[]
 *
 * @implements Cacheable<ReturnStructure>
 * @implements SerializableCacheable<ReturnStructure, SerializedStructure>
 */
class AuthorPopularBooksQuery implements Cacheable, SerializableCacheable
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

        return $books;
    }

    public function serialize(mixed $value): array
    {
        return $value->pluck('id')->all();
    }

    /**
     * @param  SerializedStructure  $value
     */
    public function unserialize(mixed $value): Collection
    {
        $booksFastAccess = array_flip($value);

        $books = Book::query()
            ->findMany($value)
            ->sortBy(fn (Book $book) => $booksFastAccess[$book->id] ?? 999)
            ->values();

        $this->setRelations($books);

        return $books;
    }

    /**
     * @param  ReturnStructure  $books
     */
    private function setRelations(Collection $books): void
    {
        $books->each->setRelation('author', $this->author);

        // Generally speaking, you can do eager loading and such in a similar fashion (for ::get and ::unserialize).
    }
}
