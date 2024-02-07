### Cacheable entities

[![Tests](https://github.com/StuDocu/cacheable-entities/actions/workflows/run-tests.yml/badge.svg)](https://github.com/StuDocu/cacheable-entities/actions/workflows/run-tests.yml)
[![Codecov](https://codecov.io/gh/StuDocu/cacheable-entities/graph/badge.svg?token=BBMKJAQ0Z1)](https://codecov.io/gh/StuDocu/cacheable-entities)

Cacheable entities is an opinionated infrastructure acts as an abstraction layer to extract away cache-related responsibilities.

## Features
- Encapsulated key and TLL management.
- Blocking/Non-blocking caching strategies.
- Serializing/Deserializing cache values on the fly.
- Easy access to real time value.

## Table of contents
<!-- TOC -->
  * [Installation](#installation)
  * [Backstory](#backstory)
  * [Usage](#usage)
    * [Defining a cacheable entity](#defining-a-cacheable-entity)
    * [Accessing a cacheable entity](#accessing-a-cacheable-entity)
      * [Strategies](#strategies)
      * [Access](#access)
    * [Serialization](#serialization)
      * [Caveat when unserializing](#caveat-when-unserializing)
    * [Purging the cache](#purging-the-cache)
    * [Async cache default value](#async-cache-default-value)
    * [Self Cacheable Entities](#self-cacheable-entities)
    * [Generic Annotation](#generic-annotation)
      * [Cacheable Generic](#cacheable-generic)
      * [SerializableCacheable](#serializablecacheable)
      * [SupportsDefaultValue](#supportsdefaultvalue)
  * [Changelog](#changelog)
  * [License](#license)
<!-- TOC -->

## Installation
You can install the package via composer:
```bash
composer require StuDocu/cacheable-entities
```

## Backstory
At [Studocu](https://www.studocu.com) we deal with a large stream of data and requests.
At some point of our growth we found ourselves drowning in cache keys and caching all over the place.
Thus, to bring a uniform approach to caching across our codebase, we cooked up an internally standardized (or as we like to call it, opinionated) infrastructure known as "Cacheable Entities". This infrastructure acts as an abstraction layer to extract away cache-related responsibilities.
We isolated this infrastructure into a standalone Laravel package and made it open-source.

Read more about the backstory at <[Cacheable Entities: A Laravel package with a story](TBD)>.

## Usage
### Defining a cacheable entity
To make a class cacheable entity, it has to implement the `StuDocu\CacheableEntities\Contracts\Cacheable` contract.

The interface implementation requires defining the following methods:
- `getCacheTTL`: Returns the TTL of the cache in seconds.
- `getCacheKey`: Returns the cache key.
- `get`: Computes the Entity value.

### Accessing a cacheable entity

#### Strategies
In some cases, you might need to have the same entity cached/accessed differently; Either blocking or non-blocking.
- Blocking Cache (Synchronous): If we don't have the value, we compute it, cache it, and serve up the result right away.
- Non-blocking Cache (Asynchronous): if we don't have the value, we dispatch a job to compute it, and return an empty state (like null, empty collection, or an empty array).

#### Access
To use any of the two caching strategies described above, we have access to two available utility classes: `SyncCache` and `AsyncCache`.
- `StuDocu\CacheableEntities\SyncCache@get`: Accepts a cacheable entity and will wait and cache the result if not pre-cached yet.
- `StuDocu\CacheableEntities\AsyncCache@get`: Accepts a cacheable entity and will dispatch a job to compute the entity value if not pre-cached already then return an empty state. Otherwise, it will return the cached value.

> ⚠️ **Important**: If you have multi servers infrastructure, and you plan to use a cacheable entity asynchronously, make sure to create and deploy the entity separately first without using `asyncCache`. Otherwise, you might have jobs' deserialization errors when deploying. Some regions might be deployed before others.

**Example**
```php
<?php

use Author;
use Book;
use Illuminate\Database\Eloquent\Collection;
use StuDocu\CacheableEntities\Contracts\Cacheable;
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;

class AuthorPopularBooksQuery implements Cacheable
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
        return Book::query()
            ->join('book_popularity_scores', 'book_popularity_scores.book_id', '=', 'books.id')
            ->where('author_id', $this->author->id)
            ->whereValid()
            ->whereHas('ratings')
            ->orderByDesc('document_popularity_scores.score')
            ->take($this->limit)
            ->get()
            ->each
            ->setRelation('author', $this->author);
    }
}

// Usage

// ...

$query = AuthorPopularBooksQuery::make($author);

// Get a non-blocking cache result in the web endpoint.
resolve(AsyncCache::class)->get($query);

// Get a blocking cache result in the API endpoint.
resolve(SyncCache::class)->get($query);
```

### Serialization
In some cases, you don't want to cache the actual value but rather the metadata of the value, for example, an array of ids.
Later, when you access the cache, you would run a query to find records by their ids.

To make a cacheable entity serializable, it has to implement the following contract `StuDocu\CacheableEntities\Contracts\SerializableCacheable`.

The interface implementation requires defining the following methods:
- `serialize(mixed $value)`: mixed: prepares the result for the cache. It will be called anytime a cacheable entity is about to be cached. The result of this method will be the cache value.
- `unserialize(mixed $value)`: mixed: restores the original state of the cached values. It will be called anytime a cache value is read. The result of this method is what will be returned as the cache value.

**Example**
```php
<?php

// [...]
use StuDocu\CacheableEntities\Contracts\SerializableCacheable;

class AuthorPopularBooksQuery implements Cacheable, SerializableCacheable
{
   // [...]
   /**
    * @param Collection<Book> $value
    * @return array<int>
    */
   public function serialize(mixed $value): array
   {
       // `$value` represents the computed value of this query; it will be what we will get when calling self::get().
       return $value->pluck('id')->all();
   }
   
    /**
     * @param  int[]  $value
     * @return  Collection<int, Book>
     */
    public function unserialize(mixed $value): Collection
    {
        // `$value` represents what we've already cached previously, it will the result of self self::serialize(...)
        
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

// Usage is still unchanged.
$query = new AuthorPopularBooksQuery($author);

// Get a non-blocking cache result in the web endpoint.
resolve(\StuDocu\CacheableEntities\AsyncCache::class)->get($query);

// Get a blocking cache result in the API endpoint.
resolve(\StuDocu\CacheableEntities\SyncCache::class)->get($query);
```

#### Caveat when unserializing
Depending on how you serialize your models, you might lose the original order when unserializing, for example, when only caching the IDs.
For Entities where the order matters, make sure to retain the original order when unserializing.

Here are some ways of doing so

```php
// Retaining the original order with array_search
$books = Book::query()
    ->findMany($value)
    ->sortBy(fn (Book $book) => array_search($book->id, $value))
    ->values();

// Retaining the original order with array_flip.
// A faster alternative than the above, using direct array access instead of `array_search`.`
$booksFastAccess = array_flip($value);
$book = Book::query()
    ->findMany($value)
    ->get()
    ->sortBy(fn (Book $book) => $booksFastAccess[$book->id] ?? 999)
    ->values();

// Retaining the original order with SQL.
$books = Book::query()
    ->orderByRaw(DB::raw('FIELD(id, ' . implode(',', $value) . ')'))
    ->get();
```
### Purging the cache
Anytime you want to invalidate the cache value of a cacheable entity, you need to use the `SyncCache::forget` method.

The use of `SyncCache` for this is because the invalidation happens on the spot.

Here are some examples of how to do so
```php
<?php
$query = new AuthorPopularBooks($author);
// Invalidate the cache (for example, in an event listener).
resolve(\StuDocu\CacheableEntities\SyncCache::class)->forget($query);
```
### Async cache default value

When using `AsyncCache` utility, it will return null if the cache is nonexistent yet.
In some cases, you might need to change the default value.

All you need to do is make the cacheable entity implement the following interface `StuDocu\CacheableEntities\Contracts\SupportDefaultCacheValue`.

The interface implementation requires defining the following method:
- `getCacheMissValue`: return default value when the entity is not cached yet.

```php
<?php

use Illuminate\Database\Eloquent\Collection;
use StuDocu\CacheableEntities\Contracts\SupportsDefaultValue;

class AuthorPopularBooks implements Cacheable, SupportsDefaultValue
{
   public function getCacheMissValue(): Collection
   {
      return Collection::empty();
   }
}
```

### Self Cacheable Entities

Normally to access the cache synchronously or asynchronously, we need the intermediate utility classes.
However, it might be the case that we need to hide this detail away for convenience.

In this case, we can use the self-cached entities concept.

To make an entity self-cacheable, it has to use the following concern `StuDocu\CacheableEntities\Concerns\SelfCacheable`

Any class with that trait has access to the following methods:

* **getCached**: Returns the cached value synchronously.
* **getCachedAsync**: Return the cached value asynchronously (it will dispatch the job if not cached yet).
* **forgetCache**: Purge the cached value.

**Example**
```php

// [...]
use StuDocu\CacheableEntities\Concerns\SelfCacheable;

class AuthorPopularBooksQuery implements Cacheable, SerializableCacheable
{
   use SelfCacheable;
   
   // [...]
}

$query = new AuthorPopularBooksQuery($author);

// Get a non-blocking cache result in the web endpoint.
$query->getCachedAsync();

// Get a blocking cache result in the API endpoint.
$query->getCached();

// Forget the cached value.
$query->forgetCache();
```
### Generic Annotation
To ensure the safe usage of the cacheable entities in terms of type, the implementation comes with generic templates.
Anytime you define a Cacheable entity, it has to also specify its generic, unless you don't have static analysis in place.

Here is an example of specifying the generic for all the contracts and concerns.

```php
/**
 * @phpstan-type ReturnStructure Collection<User>
 * @implements Cacheable<ReturnStructure>
 * @implements SerializableCacheable<ReturnStructure, string>
 * @implements SupportsDefaultValue<ReturnStructure>
 */
class CourseQuery implements Cacheable, SerializableCacheable, SupportsDefaultValue
{
    /** @phpstan-use SelfCacheable<ReturnStructure> */
    use SelfCacheable;
}
```
#### Cacheable Generic
This contract accepts one generic definition `<TReturn>`, which is what the entity will return when calling `get` to compute its value.

#### SerializableCacheable
This contract accepts two generic definitions `<TUnserialized, TSerialized>`
* `TUnserialized`: The type that will be returned when we unseralized the cache value. It should be the same shape as `TReturn` to ensure consistency.
* `TSerialized`: the type that will be returned when we serialize the result.

#### SupportsDefaultValue
This contract accepts one generic definition `<TDefault>`,
which is what the entity will return when missing the cache while using the `AsyncCache` utility.

## Changelog
Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
