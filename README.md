
# DoctrineQuerySearchHelper

This package aims to facilitate the creation of dynamic WHERE clauses
when using ***Doctrine\ORM\Querybuilder*** or ***Doctrine\DBAL\Querybuilder***

He provides :
- a Querybuilder helper *( ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder )*
- some helpers to define search parameters criteria



## Installation

> DoctrineQuerySearchHelper require at least PHP 7.4 

Run the following command to install it in your application:

```console
$ composer require exegeseit/doctrinequerysearch-helper
```

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
        ];

        $markets = $em->getRepository(Market::class)->fetchMarketQb($search)
                     ->getQuery()->useQueryCache(true)
                     ->getResult()
                     ;

        // ...
    }
}
```


