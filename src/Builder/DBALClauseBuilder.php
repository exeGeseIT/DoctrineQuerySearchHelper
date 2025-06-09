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

        foreach ($this->searchFields as $searchKey => $field) {
            if (!isset($whereFilters[$searchKey])) {
                continue;
            }

            /**
             * @var TWhere $criteria
             */
            foreach ($whereFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_i%d', $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, [FilterExprFn::In, FilterExprFn::NotIn]) && is_array($_value)) {
                    $i = 0;
                    $pattern = array_shift($_value);
                    $parameter = sprintf('%s_%d', $_searchKey, $i++);

                    /** @var CompositeExpression $compositeExpression */
                    $compositeExpression = $this->queryBuilder
                        ->setParameter($parameter, $pattern)
                        ->expr()->{$expFn->value()}($field, ':' . $parameter);
                    $orStatements = $this->queryBuilder->expr()->or($compositeExpression);

                    foreach ($_value as $_pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);

                        /** @var CompositeExpression $_compositeExpression */
                        $_compositeExpression = $this->queryBuilder
                            ->setParameter($parameter, $_pattern)
                            ->expr()->{$expFn->value()}($field, ':' . $parameter);
                        $orStatements = $orStatements->with($compositeExpression);
                    }

                    $this->queryBuilder->andWhere($orStatements);
                } else {
                    /** @var CompositeExpression $compositeExpression */
                    $compositeExpression = $this->queryBuilder->expr()->{$expFn->value()}($field, ':' . $_searchKey);
                    $this->queryBuilder->andWhere($compositeExpression);

                    if (SearchHelper::NULL_VALUE !== $_value) {
                        $_typeValue = ParameterType::STRING;

                        if (is_array($_value)) {
                            $_typeValue = is_int($_value[0]) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;
                        }

                        $this->queryBuilder->setParameter($_searchKey, $_value, $_typeValue);
                    }
                }
            }
        }

        $this->addCompositeDBALParts($compositeWhereFilters);
    }

    /**
     * @param array<string, array<string, list<TWhere>>> $compositeWhereFilters
     */
    private function addCompositeDBALParts(array $compositeWhereFilters): void
    {
        $iteration = 0;

        /** @ var array<string, TWhere[]> $compositeFilters */
        foreach ($compositeWhereFilters as $encodedCompositeKey => $compositeFilters) {
            ++$iteration;

            $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
            $compositeFilterKey = $demuxedFilter['filter'];

            if (!SearchFilter::isCompositeFilter($compositeFilterKey)) {
                continue;
            }

            [$radicalKey, $compositePartAdder, $compositeType] = match ($compositeFilterKey) {
                // .. AND (field1 ... OR field2 ...)
                SearchFilter::COMPOSITE_AND_OR => ['ANDOR', 'andWhere', CompositeExpression::TYPE_OR],
                // .. OR (field1 ... AND field2 ...)
                SearchFilter::COMPOSITE_OR => ['OR', 'orWhere', CompositeExpression::TYPE_AND],
                // SearchFilter::COMPOSITE_AND = .. AND (field1 ... AND field2 ...)
                default => ['AND', 'andWhere', CompositeExpression::TYPE_AND],
            };

            $radical = sprintf('%s%d_%s', $radicalKey, $iteration, bin2hex(random_bytes(15)));

            if (($ANDStatements = $this->getCompositeDBALStatement($compositeFilters, $radical, $compositeType)) instanceof CompositeExpression) {
                $this->queryBuilder->{$compositePartAdder}($ANDStatements);
            }
        }
    }

    /**
     * @param array<string, list<TWhere>> $compositeFilters
     * @param CompositeExpression::TYPE_* $compositeType
     */
    private function getCompositeDBALStatement(array $compositeFilters, string $radical, string $compositeType): ?CompositeExpression
    {
        $CompositeStatement = null;
        foreach ($this->searchFields as $searchKey => $field) {
            if (!isset($compositeFilters[$searchKey])) {
                continue;
            }

            if (!$CompositeStatement instanceof CompositeExpression) {
                $CompositeStatement = match ($compositeType) {
                    CompositeExpression::TYPE_AND => $this->queryBuilder->expr()->and('1=1'),
                    CompositeExpression::TYPE_OR => $this->queryBuilder->expr()->or('1=0'),
                };
            }

            foreach ($compositeFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_%s_i%d', $radical, $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, [FilterExprFn::In, FilterExprFn::NotIn]) && is_array($_value)) {
                    $i = 0;
                    $pattern = array_shift($_value);
                    $parameter = sprintf('%s_%d', $_searchKey, $i++);

                    /** @var CompositeExpression $compositeExpression */
                    $compositeExpression = $this->queryBuilder
                        ->setParameter($parameter, $pattern)
                        ->expr()->{$expFn->value()}($field, ':' . $parameter);
                    $orStatements = $this->queryBuilder->expr()->or($compositeExpression);

                    foreach ($_value as $_pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);

                        /** @var CompositeExpression $_compositeExpression */
                        $_compositeExpression = $this->queryBuilder
                            ->setParameter($parameter, $_pattern)
                            ->expr()->{$expFn->value()}($field, ':' . $parameter);
                        $orStatements = $orStatements->with($_compositeExpression);
                    }

                    $CompositeStatement = $CompositeStatement->with($orStatements);
                } else {
                    /** @var CompositeExpression $compositeExpression */
                    $compositeExpression = $this->queryBuilder->expr()->{$expFn->value()}($field, ':' . $_searchKey);
                    $CompositeStatement = $CompositeStatement->with($compositeExpression);

                    if (SearchHelper::NULL_VALUE !== $_value) {
                        $_typeValue = ParameterType::STRING;

                        if (is_array($_value)) {
                            $_typeValue = is_int($_value[0]) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;
                        }

                        $this->queryBuilder->setParameter($_searchKey, $_value, $_typeValue);
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
