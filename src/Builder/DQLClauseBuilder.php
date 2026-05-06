<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;

/**
 * Constructeur de clauses DQL pour la construction de requêtes Doctrine.
 *
 * Cette classe permet de construire dynamiquement des clauses WHERE et ORDER BY
 * en se basant sur les critères de recherche fournis.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
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
     * @param array<string, list<WhereCriteria>> $whereFilters
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

    private function addWhereCondition(string $field, string $parameterKey, WhereCriteria $whereCriteria): void
    {
        if ($whereCriteria->shouldExpandArrayAsOrConditions()) {
            /** @var list<int|float|string> $value */
            $value = $whereCriteria->value;
            $this->handleArrayValue($field, $parameterKey, $whereCriteria->filterExprFn, $value, $whereCriteria->escapedLike);

            return;
        }

        $this->handleSingleValue($field, $parameterKey, $whereCriteria->filterExprFn, $whereCriteria->value, $whereCriteria->escapedLike);
    }

    /**
     * @param list<int|float|string> $values
     */
    private function handleArrayValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, array $values, bool $escapedLike = false): void
    {
        $orx = $this->queryBuilder->expr()->orX();

        foreach ($values as $index => $value) {
            $parameter = sprintf('%s_%d', $parameterKey, $index);
            $this->queryBuilder->setParameter($parameter, $value);

            $orx->add($this->buildExpression($field, ':'.$parameter, $filterExprFn, $escapedLike));
        }

        $this->queryBuilder->andWhere($orx);
    }

    private function handleSingleValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, mixed $value, bool $escapedLike = false): void
    {
        $this->queryBuilder->andWhere(
            $this->buildExpression($field, ':'.$parameterKey, $filterExprFn, $escapedLike)
        );

        if (SearchHelper::NULL_VALUE !== $value) {
            $this->queryBuilder->setParameter($parameterKey, $value);
        }
    }

    private function buildExpression(string $field, string $parameter, FilterExprFn $filterExprFn, bool $escapedLike = false): string
    {
        $expression = match ($filterExprFn) {
            FilterExprFn::IsNull, FilterExprFn::IsNotNull => $this->queryBuilder->expr()->{$filterExprFn->value}($field),
            default => $this->queryBuilder->expr()->{$filterExprFn->value}($field, $parameter),
        };

        if ($escapedLike && in_array($filterExprFn, [FilterExprFn::Like, FilterExprFn::NotLike], true)) {
            return sprintf("%s ESCAPE '%s'", $expression, SearchHelper::LIKE_ESCAPE_CHARACTER);
        }

        return (string) $expression;
    }

    /**
     * @param array<string, array<string, list<WhereCriteria>>> $compositeWhereFilters
     */
    private function processCompositeWhereFilters(array $compositeWhereFilters): void
    {
        foreach ($compositeWhereFilters as $encodedCompositeKey => $compositeFilters) {
            $this->addCompositePart($encodedCompositeKey, $compositeFilters);
        }
    }

    /**
     * @param array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $compositeFilters
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

        $compositeStatement = $this->getCompositeDQLStatement($encodedCompositeKey, $compositeFilters);
        $this->queryBuilder->{$compositePartAdder}($compositeStatement);
    }

    /**
     * @param array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $compositeFilters
     */
    private function getCompositeDQLStatement(string $encodedCompositeKey, array $compositeFilters): Composite
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];
        $token = $demuxedFilter['key'];

        [$radicalKey, $compositeStatement] = match ($compositeFilterKey) {
            SearchFilter::COMPOSITE_AND_OR => ['ANDOR', $this->queryBuilder->expr()->orX()],
            SearchFilter::COMPOSITE_OR => ['OR', $this->queryBuilder->expr()->andX()],
            default => ['AND', $this->queryBuilder->expr()->andX()],
        };

        $radical = sprintf('%s%s_%s', $radicalKey, $token, $this->getToken());

        foreach ($compositeFilters as $searchKey => $stack) {
            $field = $this->searchFields[$searchKey] ?? null;

            if (null === $field) {
                if (!SearchFilter::isCompositeEncodedFilter($searchKey)) {
                    continue;
                }

                /** @var array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $stack */
                $compositeStatement->add($this->getCompositeDQLStatement($searchKey, $stack));
                continue;
            }

            /** @var list<WhereCriteria> $stack */
            foreach ($stack as $index => $criteria) {
                $searchKeyParameter = sprintf('%s_%s_i%d', $radical, $searchKey, $index);

                if ($criteria->shouldExpandArrayAsOrConditions()) {
                    /** @var list<int|float|string> $value */
                    $value = $criteria->value;
                    $orStatements = $this->queryBuilder->expr()->orX();

                    foreach ($value as $valueIndex => $pattern) {
                        $parameter = sprintf('%s_%d', $searchKeyParameter, $valueIndex);
                        $this->queryBuilder->setParameter($parameter, $pattern);

                        $orStatements->add(
                            $this->buildExpression($field, ':'.$parameter, $criteria->filterExprFn, $criteria->escapedLike)
                        );
                    }

                    $compositeStatement->add($orStatements);
                    continue;
                }

                $compositeStatement->add(
                    $this->buildExpression($field, ':'.$searchKeyParameter, $criteria->filterExprFn, $criteria->escapedLike)
                );

                if (SearchHelper::NULL_VALUE !== $criteria->value) {
                    $this->queryBuilder->setParameter($searchKeyParameter, $criteria->value);
                }
            }
        }

        return $compositeStatement;
    }

    private function initializeDQLOrderby(?string $paginatorSort): void
    {
        $sorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] === $sorts) {
            return;
        }

        $initialOrder = $this->queryBuilder->getDQLPart('orderBy');

        if (!is_iterable($initialOrder)) {
            $initialOrder = [$initialOrder];
        }

        $this->queryBuilder->resetDQLPart('orderBy');

        foreach ($sorts as $sort) {
            $this->queryBuilder->addOrderBy($sort->field, $sort->direction);
        }

        /** @var OrderBy $sort */
        foreach ($initialOrder as $sort) {
            $this->queryBuilder->addOrderBy($sort);
        }
    }
}
