<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
 * Constructeur de clauses DQL pour la construction de requêtes Doctrine.
 *
 * Cette classe permet de construire dynamiquement des clauses WHERE et ORDER BY
 * en se basant sur les critères de recherche fournis.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 * @phpstan-import-type TWhere from SearchHelper
 *
 * @extends AbstractClauseBuilderProcessor<QueryBuilder>
 */
class DQLClauseBuilder extends AbstractClauseBuilderProcessor
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
    ) {
    }

    /**
     * @param TSearch|null $search
     */
    public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder
    {
        $this->setDQLWhereClause($search);
        $this->initializeDQLOrderby($paginatorSort);

        return $this->queryBuilder;
    }

    /**
     * @param TSearch|null $search
     */
    private function setDQLWhereClause(?array $search): void
    {
        $whereClauses = $this->getWhereFilters($search);

        if (null === $whereClauses) {
            return;
        }

        [$whereFilters, $compositeWhereFilters] = $whereClauses;
        $this->processSimpleWhereFilters($whereFilters);
        $this->processCompositeWhereFilters($compositeWhereFilters);
    }

    /**
     * @param array<string, list<TWhere>> $whereFilters
     */
    private function processSimpleWhereFilters(array $whereFilters): void
    {
        foreach ($whereFilters as $searchKey => $whereFilter) {
            $field = $this->searchFields[$searchKey] ?? null;

            if (null === $field) {
                continue;
            }

            foreach ($whereFilter as $index => $criteria) {
                $parameterKey = sprintf('%s_i%d', $searchKey, $index);
                $this->addWhereCondition($field, $parameterKey, $criteria);
            }
        }
    }

    /**
     * @param TWhere $criteria
     */
    private function addWhereCondition(string $field, string $parameterKey, array $criteria): void
    {
        $expFn = $criteria['expFn'];
        $value = $criteria['value'];

        if (!in_array($expFn, [FilterExprFn::In, FilterExprFn::NotIn]) && is_array($value)) {
            $this->handleArrayValue($field, $parameterKey, $expFn, $value);
        } else {
            $this->handleSingleValue($field, $parameterKey, $expFn, $value);
        }
    }

    /**
     * @param list<int|float|string> $values
     */
    private function handleArrayValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, array $values): void
    {
        $orx = $this->queryBuilder->expr()->orX();
        foreach ($values as $i => $value) {
            $parameter = sprintf('%s_%d', $parameterKey, $i);
            $orx->add(
                $this->queryBuilder
                    ->setParameter($parameter, $value)
                    ->expr()->{$filterExprFn->value()}($field, ':' . $parameter)
            );
        }

        $this->queryBuilder->andWhere($orx);
    }

    private function handleSingleValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, mixed $value): void
    {
        $this->queryBuilder->andWhere(
            $this->queryBuilder->expr()->{$filterExprFn->value()}($field, ':' . $parameterKey)
        );

        if (SearchHelper::NULL_VALUE !== $value) {
            $this->queryBuilder->setParameter($parameterKey, $value);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $compositeWhereFilters
     */
    private function processCompositeWhereFilters(array $compositeWhereFilters): void
    {
        foreach ($compositeWhereFilters as $encodedCompositeKey => $compositeFilters) {
            $this->addCompositePart($encodedCompositeKey, $compositeFilters);
        }
    }

    /**
     * array<string, list<TWhere>|array<string, list<TWhere>> $compositeFilters.
     *
     * @param array<string, mixed> $compositeFilters
     */
    private function addCompositePart(string $encodedCompositeKey, array $compositeFilters): void
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];

        $compositePartAdder = match ($compositeFilterKey) {
            SearchFilter::COMPOSITE_AND_OR => 'andWhere',
            SearchFilter::COMPOSITE_OR => 'orWhere',
            default => 'andWhere',
        };

        $CompositeStatement = $this->getCompositeDQLStatement($encodedCompositeKey, $compositeFilters);
        $this->queryBuilder->{$compositePartAdder}($CompositeStatement);
    }

    /**
     * array<string, list<TWhere>|array<string, list<TWhere>> $compositeFilters.
     *
     * @param array<string, mixed> $compositeFilters
     */
    private function getCompositeDQLStatement(string $encodedCompositeKey, array $compositeFilters): Composite
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];
        $token = $demuxedFilter['key'];

        [$radicalKey, $CompositeStatement] = match ($compositeFilterKey) {
            // .. AND (field1 ... OR field2 ...)
            SearchFilter::COMPOSITE_AND_OR => ['ANDOR', $this->queryBuilder->expr()->orX()],
            // .. OR (field1 ... AND field2 ...)
            SearchFilter::COMPOSITE_OR => ['OR', $this->queryBuilder->expr()->andX()],
            // SearchFilter::COMPOSITE_AND = .. AND (field1 ... AND field2 ...)
            default => ['AND', $this->queryBuilder->expr()->andX()],
        };

        $radical = sprintf('%s%s_%s', $radicalKey, $token, $this->getToken());

        foreach ($compositeFilters as $searchKey => $stack) {
            $field = $this->searchFields[$searchKey] ?? null;

            if (null === $field) {
                if (!SearchFilter::isCompositeEncodedFilter($searchKey)) {
                    unset($compositeFilters[$searchKey]);
                    continue;
                }

                /** @var array<string, mixed> $stack */
                $CompositeStatement->add($this->getCompositeDQLStatement($searchKey, $stack));
                continue;
            }

            /**
             * @var list<TWhere> $stack
             */
            foreach ($stack as $index => $criteria) {
                $_searchKey = sprintf('%s_%s_i%d', $radical, $searchKey, $index);
                $expFn = $criteria['expFn'];
                $value = $criteria['value'];

                if (!in_array($expFn, [FilterExprFn::In, FilterExprFn::NotIn]) && is_array($value)) {
                    $orStatements = $this->queryBuilder->expr()->orX();
                    foreach ($value as $i => $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i);
                        $orStatements->add(
                            $this->queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn->value()}($field, ':' . $parameter)
                        );
                    }

                    $CompositeStatement->add($orStatements);
                } else {
                    $CompositeStatement->add(
                        $this->queryBuilder->expr()->{$expFn->value()}($field, ':' . $_searchKey)
                    );

                    if (SearchHelper::NULL_VALUE !== $value) {
                        $this->queryBuilder->setParameter($_searchKey, $value);
                    }
                }
            }
        }

        return $CompositeStatement;
    }

    private function initializeDQLOrderby(?string $paginatorSort): void
    {
        $tSorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] !== $tSorts) {
            $_initial_order = $this->queryBuilder->getDQLPart('orderBy');

            if (!is_iterable($_initial_order)) {
                $_initial_order = [$_initial_order];
            }

            $this->queryBuilder->resetDQLPart('orderBy');
            foreach ($tSorts as $tSort) {
                $this->queryBuilder->addOrderBy($tSort['sort'], $tSort['direction']);
            }

            /** @var OrderBy $sort */
            foreach ($_initial_order as $sort) {
                $this->queryBuilder->addOrderBy($sort);
            }
        }
    }
}
