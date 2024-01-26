<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class QueryClauseBuilder
{
    private QueryBuilder|QueryBuilderDBAL $qb;

    /**
     * @var array<string, string>
     */
    private array $searchFields = [];

    /**
     * @var array<string>
     */
    private array $defaultLike = [];

    private function __construct()
    {
    }

    public static function getInstance(QueryBuilder|QueryBuilderDBAL $qb): self
    {
        $instance = new self();
        $instance->qb = $qb;

        return $instance;
    }

    /**
     * @param array<string, string> $searchFields [searchKey => field]
     */
    public function setSearchFields(iterable $searchFields): self
    {
        foreach ($searchFields as $searchKey => $field) {
            $this->searchFields[$searchKey] = $field;
        }

        return $this;
    }

    /**
     * If these searchKey appear in the $search array without any filter a LIKE filter is implicitly applied.
     * In other words, for such a searchKey, these two definitions are equivalent:
     *    SearchFilter::filter('default_like_searchkey') => 'foo',
     *    SearchFilter::like('default_like_searchkey') => 'foo',.
     *
     * @param array<string, string> $likeFields [searchKey => field]
     */
    public function setDefaultLikeFields(iterable $likeFields): self
    {
        foreach ($likeFields as $searchKey => $field) {
            $this->searchFields[$searchKey] = $field;
            $this->defaultLike[] = $searchKey;
        }

        return $this;
    }

    /**
     * @param TSearch|null $search
     */
    public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL
    {
        $searchFilters = $this->getSearchFilters($search);

        SearchHelper::initializeQbPaginatorOrderby($this->qb, $paginatorSort);
        SearchHelper::setQbSearchClause($this->qb, $searchFilters, $this->searchFields);

        return $this->qb;
    }

    /**
     * @param TSearch|null $search
     *
     * @return TSearch
     */
    private function getSearchFilters(?array $search): array
    {
        if (null === $search || [] === $search) {
            return [];
        }

        if ([] === $this->defaultLike) {
            return $search;
        }

        $_search = [];
        foreach ($search as $searchfilter => $value) {
            $m = SearchFilter::decodeSearchfilter($searchfilter);

            if (empty($m['filter']) && in_array($m['key'], $this->defaultLike)) {
                $_search[SearchFilter::like($m['key'])] = $value;
            } else {
                $_search[$searchfilter] = $value;
            }
        }

        return $_search;
    }
}
