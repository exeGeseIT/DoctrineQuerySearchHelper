<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\SortCriteria;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 *
 * @template TQueryBuilder of QueryBuilder|QueryBuilderDBAL
 *
 * @implements ClauseBuilderInterface<TQueryBuilder>
 */
abstract class AbstractClauseBuilderProcessor implements ClauseBuilderInterface
{
    /**
     * @var array<string, string>
     */
    protected array $searchFields = [];

    /**
     * @var list<string>
     */
    private array $defaultLike = [];

    /**
     * @param TSearch|null $search
     *
     * @return TQueryBuilder
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

        $normalizedSearch = [];
        foreach ($search as $searchfilter => $value) {
            $decodedFilter = SearchFilter::decodeSearchfilter($searchfilter);

            if (
                in_array($decodedFilter['key'], $this->defaultLike, true)
                && $this->isDefaultFilter($decodedFilter['filter'])
            ) {
                $normalizedSearch[SearchFilter::like($decodedFilter['key'])] = $value;
                continue;
            }

            $normalizedSearch[$searchfilter] = $value;
        }

        return $normalizedSearch;
    }

    private function isDefaultFilter(string $filter): bool
    {
        return '' === $filter || SearchFilter::FILTER === $filter;
    }

    /**
     * @return list<SortCriteria>
     */
    protected function normalizePaginatorSort(string $paginatorSort): array
    {
        $sorts = [];
        foreach (explode(',', $paginatorSort) as $order) {
            $normalizedOrder = trim($order);

            if ('' === $normalizedOrder) {
                continue;
            }

            $parts = preg_split('/\s+/', $normalizedOrder);

            if (false === $parts) {
                continue;
            }

            $field = $parts[0];
            $direction = strtoupper($parts[1] ?? 'ASC');

            if (!(bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field)) {
                continue;
            }

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }

            $sorts[] = new SortCriteria(
                field: $field,
                direction: $direction,
            );
        }

        return $sorts;
    }

    /**
     * @param TSearch|null $search
     *
     * @return array{0: array<string, list<WhereCriteria>>, 1: array<string, array<string, list<WhereCriteria>>>}|null
     */
    protected function getWhereFilters(?array $search): ?array
    {
        $searchHelper = new SearchHelper($this->getSearchFilters($search));
        $clauseFilters = $searchHelper->getClauseFilters();

        if ([] === $clauseFilters) {
            return null;
        }

        /** @var array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $whereFilters */
        $whereFilters = $clauseFilters;
        $compositeWhereFilters = [];

        foreach ($clauseFilters as $searchKey => $filters) {
            if (SearchFilter::isCompositeEncodedFilter($searchKey)) {
                /** @var array<string, list<WhereCriteria>> $filters */
                $compositeWhereFilters[$searchKey] = $filters;
                unset($whereFilters[$searchKey]);
            }
        }

        /** @var array<string, list<WhereCriteria>> $whereFilters */
        /** @var array<string, array<string, list<WhereCriteria>>> $compositeWhereFilters */
        return [$whereFilters, $compositeWhereFilters];
    }

    protected function getToken(): string
    {
        return bin2hex(random_bytes(15));
    }
}
