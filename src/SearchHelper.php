<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-type TSearchvalue  array<int|string>|bool|int|string
 * @phpstan-type TSearch       array<string, TSearchvalue|array<string, TSearchvalue>>
 * @phpstan-type TWhere        array{'expFn': string, 'value': TSearchvalue}
 * @phpstan-type TSort         array{'sort': string, 'direction': string}
 */
class SearchHelper
{
    private const NULL_VALUE = '_NULL_';

    private const COMPOSITE_FILTERS = [
        SearchFilter::AND => 'and',
        SearchFilter::OR => 'or',
        SearchFilter::AND_OR => 'andor',
    ];

    /**
     * @param array<string|int>|string|int $searched
     *
     * @return string|string[]
     */
    public static function sqlSearchString(string|int|array $searched, bool $strict = false): string|array
    {
        $_s = [];
        $stack = is_iterable($searched) ? $searched : [$searched];
        foreach ($stack as $searchedValue) {
            $_s[] = match ($strict) {
                true => (string) $searchedValue,
                default => '%' . str_replace(['%', '_'], ['\%', '\_'], trim(\mb_strtolower((string) $searchedValue))) . '%',
            };
        }

        return is_iterable($searched) ? $_s : $_s[0];
    }

    /**
     * @param TSearch               $search
     * @param array<string, string> $fields
     */
    public static function setQbSearchClause(QueryBuilder|QueryBuilderDBAL $qb, array $search, array $fields = []): void
    {
        if ($qb instanceof QueryBuilder) {
            self::setQbDQLSearchClause($qb, self::parseSearchParameters($search), $fields);
        } else {
            self::setQbDBALSearchClause($qb, self::parseSearchParameters($search), $fields);
        }
    }

    /**
     * ./!\ CAUTION WITH DBAL\QueryBuilder /!\.
     *
     * Default orderBy statement must be part of the $paginatorSort parameter, otherwise it will be lost when sorting.
     */
    public static function initializeQbPaginatorOrderby(QueryBuilder|QueryBuilderDBAL $qb, ?string $paginatorSort): void
    {
        $tSorts = self::normalizePaginatorSort($paginatorSort ?? '');
        if ($qb instanceof QueryBuilder) {
            self::initializeQbPaginatorOrderbyDQL($qb, $tSorts);
        } else {
            self::initializeQbPaginatorOrderbyDBAL($qb, $tSorts);
        }
    }

    /**
     * @param array<int, TSort> $tSorts
     */
    private static function initializeQbPaginatorOrderbyDQL(QueryBuilder $queryBuilder, array $tSorts): void
    {
        if ([] !== $tSorts) {
            $_initial_order = $queryBuilder->getDQLPart('orderBy');
            $_initial_order = is_iterable($_initial_order) ? $_initial_order : [$_initial_order];

            $queryBuilder->resetDQLPart('orderBy');
            foreach ($tSorts as $tSort) {
                $queryBuilder->addOrderBy($tSort['sort'], $tSort['direction']);
            }

            /** @var OrderBy $sort */
            foreach ($_initial_order as $sort) {
                $queryBuilder->addOrderBy($sort);
            }
        }
    }

    /** ******************
     *
     *  DBAL Helpers.
     *
     ****************** */

    /**
     * @param array<string, TWhere[]|array<string, TWhere[]>> $whereFilters
     * @param array<string, string>                           $fields
     */
    private static function setQbDBALSearchClause(QueryBuilderDBAL $queryBuilderDBAL, array $whereFilters, array $fields): void
    {
        foreach ($fields as $searchKey => $field) {
            if (!isset($whereFilters[$searchKey])) {
                continue;
            }

            foreach ($whereFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_i%d', $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, ['in', 'notIn']) && is_array($_value)) {
                    $i = 0;
                    $pattern = array_shift($_value);
                    $parameter = sprintf('%s_%d', $_searchKey, $i++);
                    $orStatements = $queryBuilderDBAL->expr()->or(
                        $queryBuilderDBAL
                            ->setParameter($parameter, $pattern)
                            ->expr()->{$expFn}($field, ':' . $parameter)
                    );
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements = $orStatements->with(
                            $queryBuilderDBAL
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $queryBuilderDBAL->andWhere($orStatements);
                } else {
                    $queryBuilderDBAL->andWhere($queryBuilderDBAL->expr()->{$expFn}($field, ':' . $_searchKey));

                    if (self::NULL_VALUE !== $_value) {
                        $_typeValue = ParameterType::STRING;

                        if (is_array($_value)) {
                            $_typeValue = is_int($_value[0]) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;
                        }

                        $queryBuilderDBAL->setParameter($_searchKey, $_value, $_typeValue);
                    }
                }
            }
        }

        self::addCompositeDBALParts($queryBuilderDBAL, $whereFilters, $fields);
    }

    /**
     * @param array<string, TWhere[]|array<string, TWhere[]>> $whereFilters
     * @param array<string, string>                           $fields
     */
    private static function addCompositeDBALParts(QueryBuilderDBAL $queryBuilderDBAL, array $whereFilters, array $fields): void
    {
        foreach (self::COMPOSITE_FILTERS as $COMPPKey => $radicalKey) {
            if (array_key_exists($COMPPKey, $whereFilters)) {
                $iteration = 0;

                /** @var array<string, TWhere[]> $compositeSearchFilters */
                foreach ($whereFilters[$COMPPKey] as $compositeSearchFilters) {
                    ++$iteration;

                    [$compositePartAdder, $compositeType] = match ($COMPPKey) {
                        // .. AND (field1 ... AND field2 ...)
                        SearchFilter::AND => ['andWhere', CompositeExpression::TYPE_AND],
                        // .. AND (field1 ... OR field2 ...)
                        SearchFilter::AND_OR => ['andWhere', CompositeExpression::TYPE_OR],
                        // .. OR (field1 ... AND field2 ...)
                        SearchFilter::OR => ['orWhere', CompositeExpression::TYPE_AND],
                    };

                    $radical = sprintf('%s%d', $radicalKey, $iteration);
                    if (($ANDStatements = self::getCompositeDBALStatement($queryBuilderDBAL, $compositeSearchFilters, $fields, $radical, $compositeType)) instanceof CompositeExpression) {
                        $queryBuilderDBAL->{$compositeType}($ANDStatements);
                    }
                }
            }
        }
    }

    /**
     * @param array<int, TSort> $tSorts
     */
    private static function initializeQbPaginatorOrderbyDBAL(QueryBuilderDBAL $queryBuilderDBAL, array $tSorts): void
    {
        if ([] !== $tSorts) {
            $queryBuilderDBAL->resetOrderBy();
            foreach ($tSorts as $tSort) {
                $queryBuilderDBAL->addOrderBy($tSort['sort'], $tSort['direction']);
            }
        }
    }

    /**
     * @return array<int, TSort>
     */
    private static function normalizePaginatorSort(string $paginatorSort): array
    {
        $paginatorSort = trim(preg_replace('/\s\s/', ' ', $paginatorSort) ?? '');
        if ('' === $paginatorSort) {
            return [];
        }

        $tSorts = [];
        foreach (explode(',', $paginatorSort) as $order) {
            $v = explode(' ', $order);
            $tSorts[] = [
                'sort' => $v[0],
                'direction' => $v[1] ?? 'ASC',
            ];
        }

        return $tSorts;
    }

    /**
     * @param TSearch $search
     *
     * @return array<string, TWhere[]|array<string, TWhere[]>>
     */
    private static function parseSearchParameters(array $search): array
    {
        $clauseFilters = [];

        $COMPOSITE_FILTERS_KEYS = array_keys(self::COMPOSITE_FILTERS);

        foreach ($search as $ckey => $value) {
            $m = SearchFilter::decodeSearchfilter($ckey);

            $key = $m['key'];
            $filter = SearchFilter::normalize($m['filter']);

            $expFn = null;

            if (is_iterable($value) && in_array($filter, $COMPOSITE_FILTERS_KEYS)) {
                if (!array_key_exists($filter, $clauseFilters)) {
                    $clauseFilters[$filter] = [];
                }

                $clauseFilters[$filter][] = self::parseSearchParameters($value);
            } elseif (SearchFilter::EQUAL === $filter) {
                $expFn = is_array($value) ? 'in' : 'eq';
            } elseif (SearchFilter::NOT_EQUAL === $filter) {
                $expFn = is_array($value) ? 'notIn' : 'neq';
            } elseif (in_array($filter, [SearchFilter::NULL, SearchFilter::NOT_NULL])) {
                $expFn = '_' === $filter ? 'isNull' : 'isNotNull';
                $value = self::NULL_VALUE;
            } elseif (!empty($value)) {
                switch ($filter) {
                    case SearchFilter::LOWER:
                        $expFn = 'lt';
                        break;

                    case SearchFilter::LOWER_OR_EQUAL:
                        $expFn = 'lte';
                        break;

                    case SearchFilter::GREATER:
                        $expFn = 'gt';
                        break;

                    case SearchFilter::GREATER_OR_EQUAL:
                        $expFn = 'gte';
                        break;

                    case SearchFilter::LIKE:
                        $expFn = 'like';
                        $value = self::sqlSearchString($value);
                        break;

                    case SearchFilter::NOT_LIKE:
                        $expFn = 'notLike';
                        $value = self::sqlSearchString($value);
                        break;

                    case SearchFilter::LIKE_STRICK:
                        $expFn = 'like';
                        $value = self::sqlSearchString($value, true);
                        break;

                    case SearchFilter::NOT_LIKE_STRICK:
                        $expFn = 'notLike';
                        $value = self::sqlSearchString($value, true);
                        break;

                    default:
                        $expFn = is_array($value) ? 'in' : 'eq';
                        break;
                }
            }

            if (null !== $expFn) {
                if (!array_key_exists($key, $clauseFilters)) {
                    $clauseFilters[$key] = [];
                }

                $clauseFilters[$key][] = [
                    'expFn' => $expFn,
                    'value' => $value,
                ];
            }
        }

        return $clauseFilters;
    }

    /** ******************
     *
     *  DQL Helpers.
     *
     ****************** */

    /**
     * @param array<string, TWhere[]|array<string, TWhere[]>> $whereFilters
     * @param array<string, string>                           $fields
     */
    private static function setQbDQLSearchClause(QueryBuilder $queryBuilder, array $whereFilters, array $fields): void
    {
        foreach ($fields as $searchKey => $field) {
            if (!isset($whereFilters[$searchKey])) {
                continue;
            }

            foreach ($whereFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_i%d', $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, ['in', 'notIn']) && is_array($_value)) {
                    $i = 0;
                    $orStatements = $queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements->add(
                            $queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $queryBuilder->andWhere($orStatements);
                } else {
                    $queryBuilder->andWhere($queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey));

                    if (self::NULL_VALUE !== $_value) {
                        $queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        self::addCompositeDQLParts($queryBuilder, $whereFilters, $fields);
    }

    /**
     * @param array<string, TWhere[]|array<string, TWhere[]>> $whereFilters
     * @param array<string, string>                           $fields
     */
    private static function addCompositeDQLParts(QueryBuilder $queryBuilder, array $whereFilters, array $fields): void
    {
        foreach (self::COMPOSITE_FILTERS as $COMPPKey => $radicalKey) {
            if (array_key_exists($COMPPKey, $whereFilters)) {
                $iteration = 0;

                /** @var array<string, TWhere[]> $compositeSearchFilters */
                foreach ($whereFilters[$COMPPKey] as $compositeSearchFilters) {
                    ++$iteration;

                    /*$t = array_filter($compositeSearchFilters, fn($k) => in_array($k, array_keys(self::COMPOSITE_FILTERS)), ARRAY_FILTER_USE_KEY);
                    dump([$COMPPKey => [$compositeSearchFilters, $t]]);*/

                    [$compositePartAdder, $compositeClass] = match ($COMPPKey) {
                        // .. AND (field1 ... AND field2 ...)
                        SearchFilter::AND => ['andWhere', Andx::class],
                        // .. AND (field1 ... OR field2 ...)
                        SearchFilter::AND_OR => ['andWhere', Orx::class],
                        // .. OR (field1 ... AND field2 ...)
                        SearchFilter::OR => ['orWhere', Andx::class],
                    };

                    $radical = sprintf('%s%d', $radicalKey, $iteration);
                    if (($CompositeStatement = self::getCompositeDQLStatement($queryBuilder, $compositeSearchFilters, $fields, $radical, $compositeClass)) instanceof Composite) {
                        $queryBuilder->{$compositePartAdder}($CompositeStatement);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, TWhere[]> $compositeFilters
     * @param array<string, string>   $fields
     * @param class-string<AndX|OrX>  $compositeClass
     */
    private static function getCompositeDQLStatement(QueryBuilder $queryBuilder, array $compositeFilters, array $fields, string $radical, string $compositeClass): ?Composite
    {
        $CompositeStatement = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($compositeFilters[$searchKey])) {
                continue;
            }

            if (!$CompositeStatement instanceof Composite) {
                $CompositeStatement = match ($compositeClass) {
                    Andx::class => $queryBuilder->expr()->andX(),
                    Orx::class => $queryBuilder->expr()->orX(),
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
                    $orStatements = $queryBuilder->expr()->orX();
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements->add(
                            $queryBuilder
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $CompositeStatement->add($orStatements);
                } else {
                    $CompositeStatement->add(
                        $queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey)
                    );

                    if (self::NULL_VALUE !== $_value) {
                        $queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        return $CompositeStatement;
    }

    /**
     * @param array<string, TWhere[]>     $compositeFilters
     * @param array<string, string>       $fields
     * @param CompositeExpression::TYPE_* $compositeType
     */
    private static function getCompositeDBALStatement(QueryBuilderDBAL $queryBuilderDBAL, array $compositeFilters, array $fields, string $radical, string $compositeType): ?CompositeExpression
    {
        $CompositeStatement = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($compositeFilters[$searchKey])) {
                continue;
            }

            if (!$CompositeStatement instanceof CompositeExpression) {
                $CompositeStatement = match ($compositeType) {
                    CompositeExpression::TYPE_AND => $queryBuilderDBAL->expr()->and('1=1'),
                    CompositeExpression::TYPE_OR => $queryBuilderDBAL->expr()->or('1=0'),
                };
            }

            foreach ($compositeFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('%s_%s_i%d', $radical, $searchKey, $index);
                $expFn = $criteria['expFn'];
                $_value = $criteria['value'];

                if (!in_array($expFn, ['in', 'notIn']) && is_array($_value)) {
                    $i = 0;
                    $pattern = array_shift($_value);
                    $parameter = sprintf('%s_%d', $_searchKey, $i++);
                    $orStatements = $queryBuilderDBAL->expr()->or(
                        $queryBuilderDBAL
                            ->setParameter($parameter, $pattern)
                            ->expr()->{$expFn}($field, ':' . $parameter)
                    );
                    foreach ($_value as $pattern) {
                        $parameter = sprintf('%s_%d', $_searchKey, $i++);
                        $orStatements = $orStatements->with(
                            $queryBuilderDBAL
                                ->setParameter($parameter, $pattern)
                                ->expr()->{$expFn}($field, ':' . $parameter)
                        );
                    }

                    $CompositeStatement = $CompositeStatement->with($orStatements);
                } else {
                    $CompositeStatement = $CompositeStatement->with(
                        $queryBuilderDBAL->expr()->{$expFn}($field, ':' . $_searchKey)
                    );

                    if (self::NULL_VALUE !== $_value) {
                        $_typeValue = ParameterType::STRING;

                        if (is_array($_value)) {
                            $_typeValue = is_int($_value[0]) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING;
                        }

                        $queryBuilderDBAL->setParameter($_searchKey, $_value, $_typeValue);
                    }
                }
            }
        }

        return $CompositeStatement;
    }
}
