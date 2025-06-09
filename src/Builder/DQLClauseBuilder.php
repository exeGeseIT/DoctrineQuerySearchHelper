<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
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
                    $orStatements = $this->queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements->add(
                            $this->queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn->value()}($field, ':' . $parameter)
                        );
                    }

                    $this->queryBuilder->andWhere($orStatements);
                } else {
                    $this->queryBuilder->andWhere($this->queryBuilder->expr()->{$expFn->value()}($field, ':' . $_searchKey));

                    if (SearchHelper::NULL_VALUE !== $_value) {
                        $this->queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        $this->addCompositeParts($compositeWhereFilters);
    }

    /**
     * @param array<string, array<string, list<TWhere>>> $compositeWhereFilters
     */
    private function addCompositeParts(array $compositeWhereFilters): void
    {
        $iteration = 0;

        foreach ($compositeWhereFilters as $encodedCompositeKey => $compositeFilters) {
            ++$iteration;

            $demuxedFilter = SearchFilter::decodeSearchfilter($encodedCompositeKey);
            $compositeFilterKey = $demuxedFilter['filter'];

            if (!SearchFilter::isCompositeFilter($compositeFilterKey)) {
                continue;
            }

            [$radicalKey, $compositePartAdder, $compositeClass] = match ($compositeFilterKey) {
                // .. AND (field1 ... OR field2 ...)
                SearchFilter::COMPOSITE_AND_OR => ['ANDOR', 'andWhere', Orx::class],
                // .. OR (field1 ... AND field2 ...)
                SearchFilter::COMPOSITE_OR => ['OR', 'orWhere', Andx::class],
                // SearchFilter::COMPOSITE_AND = .. AND (field1 ... AND field2 ...)
                default => ['AND', 'andWhere', Andx::class],
            };

            $radical = sprintf('%s%d_%s', $radicalKey, $iteration, $this->getToken());

            if (($CompositeStatement = $this->getCompositeDQLStatement($compositeFilters, $radical, $compositeClass)) instanceof Composite) {
                $this->queryBuilder->{$compositePartAdder}($CompositeStatement);
            }
        }
    }

    /**
     * @param array<string, list<TWhere>> $compositeFilters
     * @param class-string<Andx|Orx>      $compositeClass
     */
    private function getCompositeDQLStatement(array $compositeFilters, string $radical, string $compositeClass): ?Composite
    {
        $CompositeStatement = null;
        foreach ($this->searchFields as $searchKey => $field) {
            if (!isset($compositeFilters[$searchKey])) {
                continue;
            }

            if (!$CompositeStatement instanceof Composite) {
                $CompositeStatement = match ($compositeClass) {
                    Andx::class => $this->queryBuilder->expr()->andX(),
                    Orx::class => $this->queryBuilder->expr()->orX(),
                    default => null,
                };

                if (!$CompositeStatement instanceof Composite) {
                    break;
                }
            }

            foreach ($compositeFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_%s_i%d', $radical, $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, [FilterExprFn::In, FilterExprFn::NotIn]) && is_array($_value)) {
                    $i = 0;
                    $orStatements = $this->queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
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

                    if (SearchHelper::NULL_VALUE !== $_value) {
                        $this->queryBuilder->setParameter($_searchKey, $_value);
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
