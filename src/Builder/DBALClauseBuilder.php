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

/**
 * Cette classe permet de construire dynamiquement des clauses WHERE et ORDER BY
 * en se basant sur les critères de recherche fournis.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 * @phpstan-import-type TWhere from SearchHelper
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
        $orx = null;
        foreach ($values as $i => $value) {
            $parameter = sprintf('%s_%d', $parameterKey, $i);

            /** @var CompositeExpression $compositeExpression */
            $compositeExpression = $this->queryBuilder
                ->setParameter($parameter, $value)
                ->expr()->{$filterExprFn->value()}($field, ':' . $parameter)
            ;

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

    private function handleSingleValue(string $field, string $parameterKey, FilterExprFn $filterExprFn, mixed $value): void
    {
        /** @var CompositeExpression $compositeExpression */
        $compositeExpression = $this->queryBuilder->expr()->{$filterExprFn->value()}($field, ':' . $parameterKey);
        $this->queryBuilder->andWhere($compositeExpression);

        if (SearchHelper::NULL_VALUE !== $value) {
            $typeValue = match (true) {
                !is_array($value) => ParameterType::STRING,
                is_int($value[0]) => ArrayParameterType::INTEGER,
                default => ArrayParameterType::STRING,
            };

            $this->queryBuilder->setParameter($parameterKey, $value, $typeValue);
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

        $compositeExpression = $this->getCompositeDBALStatement($encodedCompositeKey, $compositeFilters);
        $this->queryBuilder->{$compositePartAdder}($compositeExpression);
    }

    /**
     * array<string, list<TWhere>|array<string, list<TWhere>> $compositeFilters.
     *
     * @param array<string, mixed> $compositeFilters
     */
    private function getCompositeDBALStatement(string $encodedCompositeKey, array $compositeFilters): CompositeExpression
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];
        $token = $demuxedFilter['key'];

        [$radicalKey, $CompositeStatement] = match ($compositeFilterKey) {
            // .. AND (field1 ... OR field2 ...)
            SearchFilter::COMPOSITE_AND_OR => ['ANDOR', $this->queryBuilder->expr()->or('1=0')],
            // .. OR (field1 ... AND field2 ...)
            SearchFilter::COMPOSITE_OR => ['OR', $this->queryBuilder->expr()->and('1=1')],
            // SearchFilter::COMPOSITE_AND = .. AND (field1 ... AND field2 ...)
            default => ['AND', $this->queryBuilder->expr()->and('1=1')],
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
                $CompositeStatement->with($this->getCompositeDBALStatement($searchKey, $stack));
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
                    $orStatements = null;
                    foreach ($value as $i => $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i);

                        /** @var CompositeExpression $compositeExpression */
                        $compositeExpression = $this->queryBuilder
                            ->setParameter($parameter, $pattern)
                            ->expr()->{$expFn->value()}($field, ':' . $parameter)
                        ;

                        if (!$orStatements instanceof CompositeExpression) {
                            $orStatements = $this->queryBuilder->expr()->or($compositeExpression);
                            continue;
                        }

                        $orStatements = $orStatements->with($compositeExpression);
                    }

                    if ($orStatements instanceof CompositeExpression) {
                        $CompositeStatement = $CompositeStatement->with($orStatements);
                    }
                } else {
                    /** @var CompositeExpression $compositeExpression */
                    $compositeExpression = $this->queryBuilder->expr()->{$expFn->value()}($field, ':' . $_searchKey);
                    $CompositeStatement = $CompositeStatement->with($compositeExpression);

                    if (SearchHelper::NULL_VALUE !== $value) {
                        $typeValue = match (true) {
                            !is_array($value) => ParameterType::STRING,
                            is_int($value[0]) => ArrayParameterType::INTEGER,
                            default => ArrayParameterType::STRING,
                        };

                        $this->queryBuilder->setParameter($_searchKey, $value, $typeValue);
                    }
                }
            }
        }

        return $CompositeStatement;
    }

    private function initializeDBALOrderby(?string $paginatorSort): void
    {
        $tSorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] !== $tSorts) {
            $this->queryBuilder->resetOrderBy();
            foreach ($tSorts as $tSort) {
                $this->queryBuilder->addOrderBy($tSort['sort'], $tSort['direction']);
            }
        }
    }
}
