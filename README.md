



# DoctrineQuerySearchHelper

This package aims to facilitate the creation of dynamic WHERE clauses
when using ***Doctrine\ORM\Querybuilder*** or ***Doctrine\DBAL\Querybuilder***

It rely on:
- ***QueryClauseBuilder***, a Querybuilder helper in charge of creating the final WHERE clause, based on an array of *$search* conditions
- ***SearchFilter***, a complete set of static helpers to define the ***$search*** conditions array 




## Installation

> DoctrineQuerySearchHelper require at least PHP 8.1 

Run the following command to install it in your application:

```console
$ composer require exegeseit/doctrinequerysearch-helper
```




## How it works / Basic usage

The basic use of this package is to create a "**fetchQb**" method in your entity's repository.
This method will receive our ***$search*** condition array as a parameter and return a fully defined Querybuilder instance (SELECT statement + WHERE statement).


Internally, an instance of ***QueryClauseBuilder*** is used to define allowed search keys and their mapping to properties of entities involved in defining the SELECT statement part of the returned QueryBuilder instance.

*The following example shows how to achieve this.*

The **$search** parameter, on the other hand, is an associative array where each line defines one of the conditions of the final WHERE clause in the form:

    searchKey_filter => searchKey_value

> The *searchKey_filter* key is generated using the appropriate **SearchFilter** helper
> as described later in the "SearchFilter Wizards" section



## Usage


>  Take a look at the ***fetchMarketQb*** method which creates a *QueryBuilder* to fetch "Market" objects.
>  In particular, see how the different "search keys" are declared, which will allow you to filter the results. 
>  It also defines an *default* ORDER BY clause


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
		    
        /**
         * Get a QueryBuilder instance and define his SELECT statement
         */ 
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

        /** 
         * Now, use $qb to  get an intance of QueryClauseBuilder 
         */
        $clauseBuilder = QueryClauseBuilder::getInstance($qb);
        $clauseBuilder
            /** 
             * First, we define valid searchKeys and their Entity property mapping
             */
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
            /** 
             * We can also define "special" searchKeys.
             * If they appear in the $search array without any filter,
             * a LIKE filter is implicitly applied
             * In other words (in this example) these two definitions are equivalent:
             *    $search[ SearchFilter::filter('manager') ] = 'Peter';
             *    $search[ SearchFilter::like('manager') ] = 'Peter';
             */
            ->setDefaultLikeFields([
                'funder' => 'fu.name',
                'organization' => 'o.name',
                'market' => 'm.name',
                'manager' => "CONCAT(u.firstname, ' ', u.lastname)",
            ])
            ;

        /** 
         * Finally, the WHERE clause of our QueryBuilder is calculated
         * and our "fully defined" QueryBuilder instance is returned. 
         */
        return $clauseBuilder->getQueryBuilder($search, $paginatorSort);
    }
}
```



>  Now, we can use our repository method to get a filtered list of Market.
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
	// Markets filtering conditions
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


## SearchFilter Wizards

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
 * Differs from SearchFilter::like() in that "$searchKey" is taken as is. 
 *   i.e.: the characters '%' and '_' are neither appended nor escaped
 * 
 * ...WHERE 1
 *    {{ AND searchKey LIKE $value }}
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::likeStrict(string $searchKey, bool $tokenize = true): string
```

```php
/**
 * Differs from SearchFilter::notLike() in that "$searchKey" is taken as is. 
 *   i.e.: the characters '%' and '_' are neither appended nor escaped
 * 
 * ...WHERE 1
 *    {{ AND searchKey NOT LIKE $value
 * 
 * @param string $searchKey
 * @param bool $tokenize  if TRUE "~<random_hash>" is added to ensure uniqueness
 * @return string
 */
SearchFilter::notLikeStrict(string $searchKey, bool $tokenize = true): string
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
 *    {{ AND ( .. AND .. AND ..) }}.
 * 
 * @return string
 */
SearchFilter::and(): string
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
