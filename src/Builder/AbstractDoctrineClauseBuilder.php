<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;

/**
 * Constructeur abstrait commun pour les clauses Doctrine ORM et DBAL.
 *
 * Cette classe contient l'algorithme commun de construction des clauses WHERE
 * simples et composites. Les différences entre ORM et DBAL sont déléguées aux
 * classes concrètes via des méthodes abstraites.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-import-type TSearch from SearchHelper
 *
 * @template TQueryBuilder of QueryBuilder|QueryBuilderDBAL
 *
 * @extends AbstractClauseBuilderProcessor<TQueryBuilder>
 */
abstract class AbstractDoctrineClauseBuilder extends AbstractClauseBuilderProcessor
{
    /**
     * @param TSearch|null $search
     *
     * @return TQueryBuilder
     */
    public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL
    {
        $this->setWhereClause($search);
        $this->initializeOrderBy($paginatorSort);

        return $this->getWrappedQueryBuilder();
    }

    /**
     * @param TSearch|null $search
     */
    private function setWhereClause(?array $search): void
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

            $this->handleArrayValue(
                $field,
                $parameterKey,
                $whereCriteria->filterExprFn,
                $value,
                $whereCriteria->escapedLike,
            );

            return;
        }

        $this->handleSingleValue(
            $field,
            $parameterKey,
            $whereCriteria->filterExprFn,
            $whereCriteria->value,
            $whereCriteria->escapedLike,
        );
    }

    /**
     * @param list<int|float|string> $values
     */
    private function handleArrayValue(
        string $field,
        string $parameterKey,
        FilterExprFn $filterExprFn,
        array $values,
        bool $escapedLike = false,
    ): void {
        $orExpression = $this->createOrComposite();

        foreach ($values as $index => $value) {
            $parameter = sprintf('%s_%d', $parameterKey, $index);

            $this->setParameter($parameter, $value);

            $orExpression = $this->addToComposite(
                $orExpression,
                $this->buildExpression($field, ':'.$parameter, $filterExprFn, $escapedLike),
            );
        }

        $this->andWhere($orExpression);
    }

    private function handleSingleValue(
        string $field,
        string $parameterKey,
        FilterExprFn $filterExprFn,
        mixed $value,
        bool $escapedLike = false,
    ): void {
        $this->andWhere(
            $this->buildExpression($field, ':'.$parameterKey, $filterExprFn, $escapedLike),
        );

        if (SearchHelper::NULL_VALUE !== $value) {
            $this->setParameter($parameterKey, $value);
        }
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
            SearchFilter::COMPOSITE_OR => 'orWhere',
            default => 'andWhere',
        };

        $stringable = $this->getCompositeStatement($encodedCompositeKey, $compositeFilters);

        $this->{$compositePartAdder}($stringable);
    }

    /**
     * @param array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $compositeFilters
     */
    private function getCompositeStatement(string $encodedCompositeKey, array $compositeFilters): \Stringable
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
        $compositeFilterKey = $demuxedFilter['filter'];
        $token = $demuxedFilter['key'];

        [$radicalKey, $compositeStatement] = match ($compositeFilterKey) {
            SearchFilter::COMPOSITE_AND_OR => ['ANDOR', $this->createOrComposite()],
            SearchFilter::COMPOSITE_OR => ['OR', $this->createAndComposite()],
            default => ['AND', $this->createAndComposite()],
        };

        $radical = sprintf('%s%s_%s', $radicalKey, $token, $this->getToken());

        foreach ($compositeFilters as $searchKey => $stack) {
            $field = $this->searchFields[$searchKey] ?? null;

            if (null === $field) {
                if (!SearchFilter::isCompositeEncodedFilter($searchKey)) {
                    continue;
                }

                /** @var array<string, list<WhereCriteria>|array<string, list<WhereCriteria>>> $stack */
                $compositeStatement = $this->addToComposite(
                    $compositeStatement,
                    $this->getCompositeStatement($searchKey, $stack),
                );

                continue;
            }

            /** @var list<WhereCriteria> $stack */
            foreach ($stack as $index => $criteria) {
                $searchKeyParameter = sprintf('%s_%s_i%d', $radical, $searchKey, $index);

                if ($criteria->shouldExpandArrayAsOrConditions()) {
                    /** @var list<int|float|string> $value */
                    $value = $criteria->value;
                    $orStatements = $this->createOrComposite();

                    foreach ($value as $valueIndex => $pattern) {
                        $parameter = sprintf('%s_%d', $searchKeyParameter, $valueIndex);

                        $this->setParameter($parameter, $pattern);

                        $orStatements = $this->addToComposite(
                            $orStatements,
                            $this->buildExpression($field, ':'.$parameter, $criteria->filterExprFn, $criteria->escapedLike),
                        );
                    }

                    $compositeStatement = $this->addToComposite($compositeStatement, $orStatements);

                    continue;
                }

                $compositeStatement = $this->addToComposite(
                    $compositeStatement,
                    $this->buildExpression($field, ':'.$searchKeyParameter, $criteria->filterExprFn, $criteria->escapedLike),
                );

                if (SearchHelper::NULL_VALUE !== $criteria->value) {
                    $this->setParameter($searchKeyParameter, $criteria->value);
                }
            }
        }

        return $compositeStatement;
    }

    /**
     * @return TQueryBuilder
     */
    abstract protected function getWrappedQueryBuilder(): QueryBuilder|QueryBuilderDBAL;

    abstract protected function initializeOrderBy(?string $paginatorSort): void;

    abstract protected function createAndComposite(): \Stringable;

    abstract protected function createOrComposite(): \Stringable;

    abstract protected function addToComposite(\Stringable $stringable, string|\Stringable $expression): \Stringable;

    abstract protected function andWhere(string|\Stringable $expression): void;

    abstract protected function orWhere(string|\Stringable $expression): void;

    abstract protected function setParameter(string $name, mixed $value): void;

    abstract protected function buildExpression(
        string $field,
        string $parameter,
        FilterExprFn $filterExprFn,
        bool $escapedLike = false,
    ): string;
}
