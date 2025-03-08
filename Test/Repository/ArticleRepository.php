<?php

namespace ExeGeseIT\Test\Repository;

use ExeGeseIT\Test\Entity\Article;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Article>
 *
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class ArticleRepository extends EntityRepository
{
//    public function __construct(ManagerRegistry $managerRegistry)
//    {
//        parent::__construct($managerRegistry, Article::class);
//    }
    

    /**
     * @param TSearch $search
     */
    public function fetchArticleQb(array $search = [], string $paginatorSort = ''): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->innerJoin('a.status', 'sa')
            ->addSelect('sa')
            ->innerJoin('a.deliveryform', 'd')
            ->addSelect('d')
            ->innerJoin('d.status', 'sd')
            ->addSelect('sd')
        ;

        $queryBuilder->addOrderBy('d.order')
            ->addOrderBy('a.reference')
        ;

        $queryClauseBuilder = QueryClauseBuilder::getInstance($queryBuilder);
        $queryClauseBuilder
            ->setSearchFields([
                'iddeliveryform' => 'd.id',
                'deliveryformStatus' => 'sd.key',
                'idarticle' => 'a.id',
                'articleStatus' => 'sa.key',

                'isremoval' => 'd.isremoval',
            ])
            ->setDefaultLikeFields([
                'article' => 'a.name',
                'contact' => 'd.contact',
                'referenceDeliveryform' => 'd.reference',
                'externalrefDeliveryform' => 'd.externalref',
                'referencearticle' => 'a.reference',
            ])
        ;

        return $queryClauseBuilder->getQueryBuilder($search, $paginatorSort);
    }
}
