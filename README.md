



# DoctrineQuerySearchHelper
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue)
![License](https://img.shields.io/badge/license-MIT-green)

DoctrineQuerySearchHelper is a lightweight helper package for building dynamic `WHERE` clauses with Doctrine ORM or Doctrine DBAL query builders.

It helps you convert a structured `$search` array into safe, reusable and centralized query conditions, while keeping your repositories easy to read and maintain.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
  - [QueryClauseBuilder](#queryclausebuilder)
  - [SearchFilter](#searchfilter)
- [Defining searchable fields](#defining-searchable-fields)
- [Basic usage with Doctrine ORM](#basic-usage-with-doctrine-orm)
- [Using SearchFilter helpers](#using-searchfilter-helpers)
  - [Equality filters](#equality-filters)
  - [LIKE filters](#like-filters)
  - [NULL filters](#null-filters)
  - [Comparison filters](#comparison-filters)
  - [Composition helpers](#composition-helpers)
- [About tokenized search keys](#about-tokenized-search-keys)
- [Security](#security)
- [License](#license)

## Requirements

- PHP 8.2 or higher
- Doctrine ORM or Doctrine DBAL


## Installation

Run the following command to install the package in your application:

```console
$ composer require exegeseit/doctrinequerysearch-helper
```

## Quick start

The following example shows a minimal usage of the package with a Doctrine ORM `QueryBuilder`.


```php
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder; 
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use App\Entity\User;
use App\Repository\UserRepository;

$qb = $entityManager->getRepository(User::class)->createQueryBuilder('u');

$clauseBuilder = QueryClauseBuilder::getInstance(qb);
$clauseBuilder->setSearchFields[
    'id' => 'u.id',
    'email' => 'u.email',
    'createdAt' => 'u.createdAt',
];

$search = [ 
    SearchFilter::equal('email') => 'john@example.com', 
];

$users = $clauseBuilder->getQueryBuilder($search)->getQuery()->getResult();
```

This produces a condition equivalent to:

```sql
WHERE u.email = :email
```


## How it works

The package is based on two main components:

- `QueryClauseBuilder`
- `SearchFilter`

A typical use case is to create a `fetchQb()` method in your repository.

This method receives a `$search` array as parameter and returns a fully configured Doctrine `QueryBuilder` instance, including both the `SELECT` statement and the dynamic `WHERE` clause.

The `$search` parameter is an associative array where each entry defines one condition of the final `WHERE` clause:

```php
$search = [ 
    SearchFilter::equal('email') => 'john@example.com', 
];
```

## QueryClauseBuilder

`QueryClauseBuilder` receives a Doctrine `QueryBuilder` instance and applies the dynamic conditions defined in the `$search` array.

It also defines the list of allowed search keys and maps them to actual Doctrine fields or expressions.

For example:

```php
$clauseBuilder->setSearchFields[
    'id' => 'u.id',
    'email' => 'u.email',
    'createdAt' => 'u.createdAt',
];
```
With this configuration, the `$search` array can use `id`, `email` and `createdAt` as search keys.

## SearchFilter

`SearchFilter` provides static helper methods used to generate the keys of the `$search` array.

For example:

```php
$search = [ 
    SearchFilter::like('email') => 'john', 
];
```

Each helper defines the type of condition to apply:

- equality
- inequality
- `LIKE`
- `NULL`
- comparison
- grouped `AND` / `OR` conditions

## Defining searchable fields

Search keys must be explicitly declared before they can be used.

This is done with `setSearchFields()`:

```php
$clauseBuilder->setSearchFields[
    'id' => 'u.id',
    'email' => 'u.email',
    'createdAt' => 'u.createdAt',
];
```

You can also define default `LIKE` fields with `setDefaultLikeFields()`:

```php
$clauseBuilder->setDefaultLikeFields([
    'firstName' => 'u.firstName',
    'lastName' => 'u.lastName',
]);
```
Default `LIKE` fields allow these two definitions to be equivalent:

```php
$search = [ 
    SearchFilter::filter('firstName') => 'john', 
];
```

```php
$search = [ 
    SearchFilter::like('firstName') => 'john', 
];
```
## Basic usage with Doctrine ORM

> The examples below use Doctrine ORM, but the same approach can also be applied with Doctrine DBAL query builders.

The following example shows a repository method that creates a `QueryBuilder` to fetch `Market` objects.

It defines:

- the base `SELECT` statement;
- the allowed search keys;
- default `LIKE` fields;
- a default `ORDER BY` clause;
- the dynamic `WHERE` clause.

```php
// src/Repository/MarketRepository.php
use App\Entity\Market;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join; 
use Doctrine\ORM\QueryBuilder; 
use Doctrine\Persistence\ManagerRegistry;
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;


class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Market::class);
    }

    public function fetchMarketQb(array $search = [], string $paginatorSort = ''): QueryBuilder
    {
		    
        /**
         * Get a QueryBuilder instance and define its SELECT statement
         */ 
        $qb = $this->createQueryBuilder('m')
                ->innerJoin('m.organization', 'o')
                    ->addSelect('o')
                ->leftJoin('m.userofmarkets', 'uof', Join::WITH, 'uof.isaccountable = 1')
                    ->addSelect('uof')
                ->leftJoin('uof.user', 'u')
                    ->addSelect('u');

        $qb->addOrderBy('m.name'); 

        /*
         * Create a QueryClauseBuilder instance from the QueryBuilder.
         */
        $clauseBuilder = QueryClauseBuilder::getInstance($qb);
        
        $clauseBuilder
            /*
             * Define valid search keys and their entity field mapping.
             */
            ->setSearchFields([
                'idmarket' => 'm.id',
                'keymarket' => 'm.key',                
                'idorganization' => 'o.id',
                'keyorganization' => 'o.key',
                'idmanager' => 'u.id',
                'isprivate' => 'm.isprivate',
                'amount' => 'm.amount',
                'createdAt' => 'm.createdAt',
                'deletedAt' => 'm.deletedAt',
            ])
            /*
             * Define default LIKE fields.
             *
             * If one of these search keys appears in the $search array through
             * SearchFilter::filter(), a LIKE filter is implicitly applied.
             */
            ->setDefaultLikeFields([
                'organization' => 'o.name',
                'market' => 'm.name',
                'name' => 'm.name',
                'manager' => "CONCAT(u.firstname, ' ', u.lastname)",
            ])
            ;

        /*
         * Apply the WHERE clause and return the configured QueryBuilder.
         */
        return $clauseBuilder->getQueryBuilder($search, $paginatorSort);
    }
}
```

You can then use this repository method from a controller or an application service:

```php
// src/Controller/SomeController.php
use App\Entity\Market;
use Doctrine\ORM\EntityManagerInterface; 
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;

class SomeController
{
    public function index(EntityManagerInterface $em) : void
    {
        $search = [
            SearchFilter::filter('idorganization') => $idorganization,
            SearchFilter::equal('manager') => $manager,
            SearchFilter::equal('isprivate') => false,
            SearchFilter::or() => [
                SearchFilter::equal('isprivate') => true,
                SearchFilter::greaterOrEqual('amount') => 5000,
            ],
        ];

        $markets = $em
            ->getRepository(Market::class)
            ->fetchMarketQb($search)
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();

        // ...
    }
}
```

## Using SearchFilter helpers

The following table summarizes the available filter helpers.

| Method                           | SQL/DQL condition                                                                   |
|----------------------------------|-------------------------------------------------------------------------------------|
| `SearchFilter::filter()`         | For not `falsy` value, applies a _default filter_ depending on the configured field |
| `SearchFilter::equal()`          | `field = value`                                                                     |
| `SearchFilter::notEqual()`       | `field <> value`                                                                    |
| `SearchFilter::like()`           | `field LIKE value`                                                                  |
| `SearchFilter::notLike()`        | `field NOT LIKE value`                                                              |
| `SearchFilter::likeStrict()`     | `field LIKE value` without automatic wildcard handling                              |
| `SearchFilter::notLikeStrict()`  | `field NOT LIKE value` without automatic wildcard handling                          |
| `SearchFilter::null()`           | `field IS NULL`                                                                     |
| `SearchFilter::notNull()`        | `field IS NOT NULL`                                                                 |
| `SearchFilter::greater()`        | `field > value`                                                                     |
| `SearchFilter::greaterOrEqual()` | `field >= value`                                                                    |
| `SearchFilter::lower()`          | `field < value`                                                                     |
| `SearchFilter::lowerOrEqual()`   | `field <= value`                                                                    |
| `SearchFilter::andOr()`          | `AND (... OR ... OR ...)`                                                           |
| `SearchFilter::and()`            | `AND (... AND ... AND ...)`                                                         |
| `SearchFilter::or()`             | `OR (... AND ... AND ...)`                                                          |

### Equality filters

####  `SearchFilter::filter()`

```php
SearchFilter::filter(string $searchKey, bool $tokenize = true): string
```

`SearchFilter::filter()` applies the _default condition_ only when the provided value is not `falsy`.

If the value is `falsy`, no filtering condition is added.
Internaly, a `falsy` value is defined as:

```php
function isFalsyValue(mixed $value): bool
{
    return null === $value || '' === $value || [] === $value || 0 === $value || false === $value;
}
```


```php
$search = [ 
    SearchFilter::filter('status') => $status,
];
```

Equivalent condition when `$status` is not `falsy`:

```sql
WHERE m.status LIKE :status -- if status is defined in the default LIKE fields
WHERE m.status = :status    -- otherwise
```

#### `SearchFilter::equal()`

```php
SearchFilter::equal(string $searchKey, bool $tokenize = true): string
```

Applies an equality condition.

```php
$search = [ 
    SearchFilter::equal('status') => $status,
];
```

Equivalent condition:

```sql
WHERE m.status = :status
```

#### `SearchFilter::notEqual()`

```php
SearchFilter::notEqual(string $searchKey, bool $tokenize = true): string
```

Applies an inequality condition.
```php
$search = [ 
    SearchFilter::notEqual('status') => $status,
];
```

Equivalent condition:
```sql
WHERE m.status <> :status
```

### LIKE filters

#### `SearchFilter::like()`

```php
SearchFilter::like(string $searchKey, bool $tokenize = true): string
```

Applies a `LIKE` condition.
```php
$search = [ 
    SearchFilter::like('name') => $name,
];
```

Equivalent condition:
```sql
WHERE m.name LIKE :name
```

#### `SearchFilter::notLike()`

```php
SearchFilter::notLike(string $searchKey, bool $tokenize = true): string
```

Applies a `NOT LIKE` condition.
```php
$search = [ 
    SearchFilter::notLike('name') => $name,
];
```

Equivalent condition:
```sql
WHERE m.name NOT LIKE :name
```


#### `SearchFilter::likeStrict()`

```php
SearchFilter::likeStrict(string $searchKey, bool $tokenize = true): string
```

Applies a strict `LIKE` condition.

Unlike `SearchFilter::like()`, the provided value is used as-is. Characters such as `%` and `_` are neither appended nor escaped.
This is useful for exact matches.

```php
$search = [ 
    SearchFilter::likeStrict('name') => $name,
];
```

Equivalent condition:
```sql
WHERE m.name LIKE :name
```

#### `SearchFilter::notLikeStrict()`

```php
SearchFilter::notLikeStrict(string $searchKey, bool $tokenize = true): string
```

Applies a strict `NOT LIKE` condition.

Unlike `SearchFilter::notLike()`, the provided value is used as-is. Characters such as `%` and `_` are neither appended nor escaped.
This is useful for exact matches.

```php
$search = [ 
    SearchFilter::notLikeStrict('name') => $name,
];
```

Equivalent condition:
```sql
WHERE m.name NOT LIKE :name
```


### NULL filters

#### `SearchFilter::null()`

```php
SearchFilter::null(string $searchKey, bool $tokenize = true): string
```

Applies an `IS NULL` condition.

```php
$search = [ 
    SearchFilter::isNull('deletedAt') => true,
];
```

Equivalent condition:
```sql
WHERE m.deletedAt IS NULL
```


#### `SearchFilter::notNull()`

```php
SearchFilter::notNull(string $searchKey, bool $tokenize = true): string
```

Applies an `IS NOT NULL` condition.

```php
$search = [ 
    SearchFilter::isNotNull('deletedAt') => true,
];
```

Equivalent condition:
```sql
WHERE m.deletedAt IS NOT NULL
```


### Comparison filters

#### `SearchFilter::greater()`

```php
SearchFilter::greater(string $searchKey, bool $tokenize = true): string
```
Applies a greater-than condition.

```php
$search = [ 
    SearchFilter::greater('amount') => $amount,
];
```

Equivalent condition:
```sql
WHERE m.amount > :amount
```

#### `SearchFilter::greaterOrEqual()`

```php
SearchFilter::greaterOrEqual(string $searchKey, bool $tokenize = true): string
```
Applies a greater-than-or-equal condition.

```php
$search = [ 
    SearchFilter::greaterOrEqual('amount') => $amount,
];
```

Equivalent condition:
```sql
WHERE m.amount >= :amount
```

#### `SearchFilter::lower()`

```php
SearchFilter::lower(string $searchKey, bool $tokenize = true): string
```
Applies a lower-than condition.

```php
$search = [ 
    SearchFilter::lower('amount') => $amount,
];
```

Equivalent condition:
```sql
WHERE m.amount < :amount
```

#### `SearchFilter::lowerOrEqual()`

```php
SearchFilter::lowerOrEqual(string $searchKey, bool $tokenize = true): string
```
Applies a lower-than-or-equal condition.

```php
$search = [ 
    SearchFilter::lowerOrEqual('amount') => $amount,
];
```

Equivalent condition:
```sql
WHERE m.amount <= :amount
```


### Range example

You can combine comparison filters to define numeric or date ranges.

```php
$search = [ 
    SearchFilter::greaterOrEqual('createdAt') => $startDate,
    SearchFilter::lowerOrEqual('createdAt') => $endDate,
 ];
```

Equivalent condition:
```sql
WHERE m.createdAt >= :startDate AND m.createdAt <= :endDate
```

Another example with a numeric range:

```php
$search = [ 
    SearchFilter::greaterOrEqual('amount') => $minAmount,
    SearchFilter::lower('amount') => $maxAmount,
 ];
```

Equivalent condition:
```sql
WHERE m.amount >= :minAmount AND m.amount < :maxAmount
```


## Composition helpers

`SearchFilter` also provides helpers to compose grouped conditions.

### `SearchFilter::andOr()`

```php
SearchFilter::andOr(): string
```

Applies a grouped `OR` condition joined to the current query with `AND`.

```php
$search = [ 
    SearchFilter::andOr() => [
        SearchFilter::equal('status') => $statusDraf,
        SearchFilter::equal('status') => $statusPending,
];
```
Equivalent condition:
```sql
WHERE 1 = 1 AND (status = :statusDraft OR status = :statusPending)
```

### `SearchFilter::and()`

```php
SearchFilter::and(): string
```

Applies a grouped `AND` condition joined to the current query with `AND`.

```php
$search = [ 
    SearchFilter::and() => [
         SearchFilter::greaterOrEqual('amount') => $amountMin,
         SearchFilter::lowerOrEqual('amount') => $amountMax,   ,
];
```

Equivalent condition:
```sql
WHERE 1 = 1 AND (amount >= :amountMin AND amount <= :amountMax)
```

### `SearchFilter::or()`

```php
SearchFilter::or(): string
```

Applies a grouped `AND` condition joined to the current query with `OR`.

```php
$search = [ 
    SearchFilter::or() => [
         SearchFilter::equal('isprivate') => true,
         SearchFilter::greaterOrEqual('amount') => $amount,   ,
];
```

Equivalent condition:
```sql
sql WHERE 1 = 1 OR (isprivate = :isprivate AND amount >= :amount)
```

## About tokenized search keys

Most `SearchFilter` methods accept a `$tokenize` argument.

```php
SearchFilter::equal(string $searchKey, bool $tokenize = true): string
```

When `$tokenize` is enabled, a random suffix is added to the generated search key in order to avoid collisions when the same field is used multiple times in the same `$search` array.

This is useful when applying several conditions to the same field.
For example:
```php
$search = [ 
    SearchFilter::andOr() => [
        SearchFilter::equal('status') => $statusDraf,
        SearchFilter::equal('status') => $statusPending,
];
```

Without tokenization, both conditions would use the same _array key_ and one condition could overwrite the other.

Tokenization makes each generated key unique.

## Security

Search values should be bound as query parameters and must not be interpolated directly into DQL or SQL strings.

This package is designed around declared search keys:

- allowed fields are explicitly configured with `setSearchFields()`;
- default `LIKE` fields are explicitly configured with `setDefaultLikeFields()`;
- user-provided search keys should not be passed directly without being mapped first.

This approach helps centralize the filtering logic and reduces the risk of exposing arbitrary fields or expressions.

## License

This package is released under the MIT License.
