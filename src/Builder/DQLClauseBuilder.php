<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
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
     * @  param array<string, TWhere[]|array<string, array<string, TWhere[]>>> $whereFilters
     *
     * @  param array<string, string>                                          $fields
     */
    /**
     * @param TSearch|null $search
     */
    private function setDQLWhereClause(?array $search): void
    {
        $whereFilters = SearchHelper::parseSearchParameters($this->getSearchFilters($search));

        if ([] === $whereFilters) {
            return;
        }

        $compositeWhereFilters = [];
        foreach ($whereFilters as $searchKey => $filters) {
            if (!array_key_exists($searchKey, $this->searchFields)) {
                $m = SearchFilter::decodeSearchfilter($searchKey);
                $COMPPKey = SearchFilter::normalize($m['filter']);

                $compositeWhereFilters = [
                    ...$compositeWhereFilters,
                    ...[
                        $COMPPKey => $filters,
                    ],
                ];
                unset($whereFilters[$searchKey]);
                continue;
            }
        }

        error_log(\Symfony\Component\VarExporter\VarExporter::export($compositeWhereFilters));
        // error_log(var_export([__METHOD__ => /*\Nette\Utils\Json::encode*/($compositeWhereFilters)], true));

        foreach ($this->searchFields as $searchKey => $field) {
            if (!isset($whereFilters[$searchKey])) {
                continue;
            }

            foreach ($whereFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_i%d', $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, ['in', 'notIn']) && is_array($_value)) {
                    $i = 0;
                    $orStatements = $this->queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements->add(
                            $this->queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $this->queryBuilder->andWhere($orStatements);
                } else {
                    $this->queryBuilder->andWhere($this->queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey));

                    if (SearchHelper::NULL_VALUE !== $_value) {
                        $this->queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        // @phpstan-ignore argument.type
        $this->addCompositeParts($compositeWhereFilters);
    }

    /**
     * @param array<string, array<string, TWhere[]>> $compositeWhereFilters
     */
    private function addCompositeParts(array $compositeWhereFilters): void
    {
        $iteration = 0;

        foreach ($compositeWhereFilters as $COMPPKey => $compositCOMPPKey) {
            ++$iteration;

            // error_log(var_export([__METHOD__   . $COMPPKey => $compositCOMPPKey], true));
            if (!in_array($COMPPKey, SearchFilter::COMPOSITE_FILTERS)) {
                continue;
            }

            [$radicalKey, $compositePartAdder, $compositeClass] = match ($COMPPKey) {
                // .. AND (field1 ... OR field2 ...)
                SearchFilter::COMPOSITE_AND_OR => ['ANDOR', 'andWhere', Orx::class],
                // .. OR (field1 ... AND field2 ...)
                SearchFilter::COMPOSITE_OR => ['OR', 'orWhere', Andx::class],
                // SearchFilter::COMPOSITE_AND = .. AND (field1 ... AND field2 ...)
                default => ['AND', 'andWhere', Andx::class],
            };

            $radical = sprintf('%s%d_%s', $radicalKey, $iteration, bin2hex(random_bytes(15)));

            if (($CompositeStatement = $this->getCompositeDQLStatement($compositCOMPPKey, $radical, $compositeClass)) instanceof Composite) {
                $this->queryBuilder->{$compositePartAdder}($CompositeStatement);
            }
        }
    }

    /**
     * @param array<string, TWhere[]> $compositeFilters
     * @param class-string<Andx|Orx>  $compositeClass
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

                if (!in_array($expFn, ['in', 'notIn']) && is_array($_value)) {
                    $i = 0;
                    $orStatements = $this->queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements->add(
                            $this->queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $CompositeStatement->add($orStatements);
                } else {
                    $CompositeStatement->add(
                        $this->queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey)
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
