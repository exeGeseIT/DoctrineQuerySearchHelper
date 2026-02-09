<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 * @phpstan-import-type TWhere  from SearchHelper
 * @phpstan-import-type TSort   from SearchHelper
 *
 * @template T of QueryBuilder|QueryBuilderDBAL
 *
 * @implements ClauseBuilderInterface<T>
 */
abstract class AbstractClauseBuilderProcessor implements ClauseBuilderInterface
{
    /**
     * @var array<string, string>
     */
    protected array $searchFields = [];

    /**
     * @var array<string>
     */
    private array $defaultLike = [];

    /**
     * @param TSearch|null $search
     *
     * @return T
     */
    abstract public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL;

    /**
     * @param array<string, string> $searchFields [searchKey => field]
     */
    public function setSearchFields(array $searchFields): static
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
    public function setDefaultLikeFields(array $likeFields): static
    {
        foreach ($likeFields as $searchKey => $field) {
            $this->searchFields[$searchKey] = $field;
            $this->defaultLike[] = $searchKey;
        }

        return $this;
    }

    /**
     * @param TSearch|null $search
     *
     * @return TSearch
     */
    protected function getSearchFilters(?array $search): array
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

            if (('' === $m['filter']) && in_array($m['key'], $this->defaultLike)) {
                $_search[SearchFilter::like($m['key'])] = $value;
            } else {
                $_search[$searchfilter] = $value;
            }
        }

        return $_search;
    }

    /**
     * @return array<int, TSort>
     */
    protected function normalizePaginatorSort(string $paginatorSort): array
    {
        $tSorts = [];
        foreach (explode(',', $paginatorSort) as $order) {
            $_order = trim($order);

            if ('' === $_order) {
                continue;
            }

            $v = explode(' ', $_order);
            $tSorts[] = [
                'sort' => $v[0],
                'direction' => $v[1] ?? 'ASC',
            ];
        }

        return $tSorts;
    }

    /**
     * @param TSearch|null $search
     *
     * @return array{0: array<string, list<TWhere>>, 1: array<string, array<string, list<TWhere>>>}|null
     */
    protected function getWhereFilters(?array $search): ?array
    {
        $searchHelper = new SearchHelper($this->getSearchFilters($search));
        $clauseFilters = $searchHelper->getClauseFilters();

        if ([] === $clauseFilters) {
            return null;
        }

        /** @var array<string, list<TWhere>|array<string, list<TWhere>>> $whereFilters */
        $whereFilters = $clauseFilters;
        $compositeWhereFilters = [];
        foreach ($clauseFilters as $searchKey => $filters) {
            if (SearchFilter::isCompositeEncodedFilter($searchKey)) {
                $compositeWhereFilters[$searchKey] = $filters;
                unset($whereFilters[$searchKey]);
            }
        }

        return [$whereFilters, $compositeWhereFilters];
    }

    protected function getToken(): string
    {
        return bin2hex(random_bytes(15));
    }
}
