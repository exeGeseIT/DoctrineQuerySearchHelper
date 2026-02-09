<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 *
 * @template-covariant  T of QueryBuilder|QueryBuilderDBAL
 */
interface ClauseBuilderInterface
{
    /**
     * @param array<string, string> $searchFields [searchKey => field]
     */
    public function setSearchFields(array $searchFields): static;

    /**
     * If these searchKey appear in the $search array without any filter a LIKE filter is implicitly applied.
     * In other words, for such a searchKey, these two definitions are equivalent:
     *    SearchFilter::filter('default_like_searchkey') => 'foo',
     *    SearchFilter::like('default_like_searchkey') => 'foo',.
     *
     * @param array<string, string> $likeFields [searchKey => field]
     */
    public function setDefaultLikeFields(array $likeFields): static;

    /**
     * @param TSearch|null $search
     *
     * @return T
     */
    public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL;
}
