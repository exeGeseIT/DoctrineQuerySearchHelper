<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;

/**
 * Cette classe permet de construire dynamiquement des clauses WHERE et ORDER BY
 * en se basant sur les critères de recherche fournis.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 *
 * @extends AbstractClauseBuilderProcessor<QueryBuilder>
 */
class DBALClauseBuilder extends AbstractClauseBuilderProcessor
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
        $this->setDBALWhereClause($search);
        $this->initializeDBALOrderby($paginatorSort);

        return $this->queryBuilder;
    }

    /**
     * @param TSearch|null $search
     */
    private function setDBALWhereClause(?array $search): void
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
        $orx = null;

        foreach ($values as $index => $value) {
            $parameter = sprintf('%s_%d', $parameterKey, $index);
            $this->queryBuilder->setParameter($parameter, $value);

            $compositeExpression = $this->buildExpression($field, ':'.$parameter, $filterExprFn, $escapedLike);

            if (!$orx instanceof CompositeExpression) {
                $orx = $this->queryBuilder->expr()->or($compositeExpression);
                continue;
            }

            $orx = $orx->with($compositeExpression);
        }

        if ($orx instanceof CompositeExpression) {
            $this->queryBuilder->andWhere($orx);
        }
    }

    private function handleSingleValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, mixed $value, bool $escapedLike = false): void
    {
        $this->queryBuilder->andWhere(
            $this->buildExpression($field, ':'.$parameterKey, $filterExprFn, $escapedLike)
        );

        if (SearchHelper::NULL_VALUE !== $value) {
            $this->queryBuilder->setParameter($parameterKey, $value, $this->resolveParameterType($value));
        }
    }

    private function buildExpression(string $field, string $parameter, FilterExprFn $filterExprFn, bool $escapedLike = false): string
    {
        $escapeChar = $escapedLike ? $this->quoteLikeEscapeCharacter() : null;

        return match ($filterExprFn) {
            FilterExprFn::IsNull, FilterExprFn::IsNotNull => $this->queryBuilder->expr()->{$filterExprFn->value}($field),
            FilterExprFn::Like, FilterExprFn::NotLike => $this->queryBuilder->expr()->{$filterExprFn->value}($field, $parameter, $escapeChar),
            default => $this->queryBuilder->expr()->{$filterExprFn->value}($field, $parameter),
        };
    }

    private function quoteLikeEscapeCharacter(): string
    {
        return "'".str_replace("'", "''", SearchHelper::LIKE_ESCAPE_CHARACTER)."'";
    }

    private function resolveParameterType(mixed $value): ArrayParameterType|ParameterType
    {
        if (is_array($value)) {
            return isset($value[0]) && is_int($value[0])
                ? ArrayParameterType::INTEGER
                : ArrayParameterType::STRING;
        }

        return match (true) {
            is_int($value) => ParameterType::INTEGER,
            is_bool($value) => ParameterType::BOOLEAN,
            default => ParameterType::STRING,
        };
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

        $compositeExpression = $this->getCompositeDBALStatement($encodedCompositeKey, $compositeFilters);
        $this->queryBuilder->{$compositePartAdder}($compositeExpression);
    }

    /**
     * @param array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $compositeFilters
     */
    private function getCompositeDBALStatement(string $encodedCompositeKey, array $compositeFilters): CompositeExpression
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];
        $token = $demuxedFilter['key'];

        [$radicalKey, $compositeStatement] = match ($compositeFilterKey) {
            SearchFilter::COMPOSITE_AND_OR => ['ANDOR', $this->queryBuilder->expr()->or('1=0')],
            SearchFilter::COMPOSITE_OR => ['OR', $this->queryBuilder->expr()->and('1=1')],
            default => ['AND', $this->queryBuilder->expr()->and('1=1')],
        };

        $radical = sprintf('%s%s_%s', $radicalKey, $token, $this->getToken());

        foreach ($compositeFilters as $searchKey => $stack) {
            $field = $this->searchFields[$searchKey] ?? null;

            if (null === $field) {
                if (!SearchFilter::isCompositeEncodedFilter($searchKey)) {
                    continue;
                }

                /** @var array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $stack */
                $compositeStatement = $compositeStatement->with($this->getCompositeDBALStatement($searchKey, $stack));
                continue;
            }

            /** @var list<WhereCriteria> $stack */
            foreach ($stack as $index => $criteria) {
                $searchKeyParameter = sprintf('%s_%s_i%d', $radical, $searchKey, $index);

                if ($criteria->shouldExpandArrayAsOrConditions()) {
                    /** @var list<int|float|string> $value */
                    $value = $criteria->value;
                    $orStatements = null;

                    foreach ($value as $valueIndex => $pattern) {
                        $parameter = sprintf('%s_%d', $searchKeyParameter, $valueIndex);
                        $this->queryBuilder->setParameter($parameter, $pattern);

                        $compositeExpression = $this->buildExpression($field, ':'.$parameter, $criteria->filterExprFn, $criteria->escapedLike);

                        if (!$orStatements instanceof CompositeExpression) {
                            $orStatements = $this->queryBuilder->expr()->or($compositeExpression);
                            continue;
                        }

                        $orStatements = $orStatements->with($compositeExpression);
                    }

                    if ($orStatements instanceof CompositeExpression) {
                        $compositeStatement = $compositeStatement->with($orStatements);
                    }

                    continue;
                }

                $compositeExpression = $this->buildExpression($field, ':'.$searchKeyParameter, $criteria->filterExprFn, $criteria->escapedLike);
                $compositeStatement = $compositeStatement->with($compositeExpression);

                if (SearchHelper::NULL_VALUE !== $criteria->value) {
                    $this->queryBuilder->setParameter($searchKeyParameter, $criteria->value, $this->resolveParameterType($criteria->value));
                }
            }
        }

        return $compositeStatement;
    }

    private function initializeDBALOrderby(?string $paginatorSort): void
    {
        $sorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] === $sorts) {
            return;
        }

        $this->queryBuilder->resetOrderBy();

        foreach ($sorts as $sort) {
            $this->queryBuilder->addOrderBy($sort->field, $sort->direction);
        }
    }
}
