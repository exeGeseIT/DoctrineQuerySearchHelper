<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\Test\Entity\Article;

/**
 * @extends EntityRepository<Article>
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class ArticleRepository extends EntityRepository
{
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
