

# DoctrineQuerySearchHelper

This package aims to facilitate the creation of dynamic WHERE clauses
when using ***Doctrine\ORM\Querybuilder*** or ***Doctrine\DBAL\Querybuilder***

He provides :
- a Querybuilder helper *( ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder )*
- some static helpers to define search parameters criteria *( ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter )*



## Installation

> DoctrineQuerySearchHelper require at least PHP 7.4 

Run the following command to install it in your application:

```console
$ composer require exegeseit/doctrinequerysearch-helper
```



## How it work

First, you need to create a "**fetch**" method in your entity's repository.
*Next example shows you how to achieve this.*

This method has a parameter **$search** which is an <key, value>array where each line will define a condition of the final WHERE clause in the form of:

    [
      searchKey_condition => condition_value,
      searchKey_condition => condition_value,
      ...
    ]

**SearchFilter** class provide some static helpers to generate $search keys



## Usage

```php
// src/Repository/MarketRepository.php
use App\Entity\Market;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
// ..
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;
// ...

class MarketRepository extends ServiceEntityRepository
{
    public function __construct(CacheInterface $cache, ManagerRegistry $registry)
    {
        parent::__construct($registry, Market::class);
    }

    public function fetchMarketQb(array $search = [], string $paginatorSort = '')
    {
        $qb = $this->createQueryBuilder('m')
                ->innerJoin('m.organization', 'o')
                    ->addSelect('o')
                ->leftJoin('m.funder', 'fu')
                    ->addSelect('fu')
                ->leftJoin('m.userofmarkets', 'uof', Join::WITH, 'uof.isaccountable = 1')
                    ->addSelect('uof')
                ->leftJoin('uof.user', 'u')
                    ->addSelect('u')
                ;

        $qb->addOrderBy('m.name');

        $clauseBuilder = QueryClauseBuilder::getInstance($qb);
        $clauseBuilder
            ->setSearchFields([
                'idmarket' => 'm.id',
                'keymarket' => 'm.key',                
                'idorganization' => 'o.id',
                'keyorganization' => 'o.key',
                'idfunder' => 'fu.id',
                'keyfunder' => 'fu.key',
                'idmanager' => 'u.id',
                'isprivate' => 'm.isprivate',
                'amount' => 'm.amount',
            ])
            ->setDefaultLikeFields([
                'funder' => 'fu.name',
                'organization' => 'o.name',
                'market' => 'm.name',
                'manager' => "CONCAT(u.firstname, ' ', u.lastname)",
            ])
            ;
        
        return $clauseBuilder->getQueryBuilder($search, $paginatorSort);
    }
}
```

>  Take a look at the ***fetchMarketQb*** method which creates a *QueryBuilder* to fetch "Market" objects.
>  In particular, see how the different "search keys" are declared, which will allow you to filter the results. 
>  It also defines an *default* ORDER BY clause



```php
// src/Controller/SomeController.php
use App\Entity\Market;
// ...
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
// ...

class SomeController
{
    public function index(EntityManagerInterface $em)
    {
	// ...
        $search = [
            SearchFilter::filter('idorganization') => $idorganization,
            SearchFilter::filter('funder') => $funder,
            SearchFilter::equal('manager') => $manager,
            SearchFilter::equal('isprivate') => false,
            SearchFilter::or() => [
                SearchFilter::equal('isprivate') => true,
                SearchFilter::greaterOrEqual('amount') => 5000,
            ],
        ];

        $markets = $em->getRepository(Market::class)->fetchMarketQb($search)
                     ->getQuery()->useQueryCache(true)
                     ->getResult()
                     ;

        // ...
    }
}
```


## SearchFilter helpers

```php
/**
 * isset($value)  ? => ...WHERE {{ searchKey = $value }}
 * !isset($value) ? => ...WHERE {{ 1 }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::filter(string $searchKey, bool $tokenize = true)
```


```php
/**
 * ...WHERE 1
 *    {{ AND searchKey = $value }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::equal(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey <> $value }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::notEqual(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey LIKE $value }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::like(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey NOT LIKE $value
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::notLike(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey IS NULL
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::null(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey IS NOT NULL
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::notNull(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey > $value
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::greater(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey >= $value
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::greaterOrEqual(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey < $value
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::lower(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * ...WHERE 1
 *    {{ AND searchKey <= $value }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::lowerOrEqual(string $searchKey, bool $tokenize = true): string
```


SearchFilter also provide two Composition helper:


```php
/**
 * ...WHERE 1
 *    {{ AND ( .. OR .. OR ..) }}
 * 
 * @return string
 */
SearchFilter::andOr(): string
```


```php
/**
 * ...WHERE 1
 *    {{ OR ( .. AND .. AND ..) }}
 * 
 * @return string
 */
SearchFilter::or(): string
```    
