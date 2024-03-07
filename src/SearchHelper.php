<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\Query\Expr\Andx;
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

        // .. AND (field1 ... OR field2 ...)
        if (array_key_exists(SearchFilter::AND_OR, $whereFilters)) {
            $iteration = 0;

            /** @var array<string, TWhere[]> $andORFilters */
            foreach ($whereFilters[SearchFilter::AND_OR] as $andORFilters) {
                ++$iteration;

                if (($ANDorStatements = self::getAndOrDBALStatement($queryBuilderDBAL, $andORFilters, $fields, $iteration)) instanceof CompositeExpression) {
                    $queryBuilderDBAL->andWhere($ANDorStatements);
                }
            }
        }

        // .. OR (field1 ... AND field2 ...)
        if (array_key_exists(SearchFilter::OR, $whereFilters)) {
            $iteration = 0;

            /** @var array<string, TWhere[]> $orFilters */
            foreach ($whereFilters[SearchFilter::OR] as $orFilters) {
                ++$iteration;

                if (($ORStatements = self::getOrDBALStatement($queryBuilderDBAL, $orFilters, $fields, $iteration)) instanceof CompositeExpression) {
                    $queryBuilderDBAL->orWhere($ORStatements);
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

        foreach ($search as $ckey => $value) {
            $m = SearchFilter::decodeSearchfilter($ckey);

            $key = $m['key'];
            $filter = SearchFilter::normalize($m['filter']);

            $expFn = null;

            if (is_iterable($value) && in_array($filter, [SearchFilter::OR, SearchFilter::AND_OR])) {
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

        // .. AND (field1 ... OR field2 ...)
        if (array_key_exists(SearchFilter::AND_OR, $whereFilters)) {
            $iteration = 0;

            /** @var array<string, TWhere[]> $andORFilters */
            foreach ($whereFilters[SearchFilter::AND_OR] as $andORFilters) {
                ++$iteration;

                if (($ANDorStatements = self::getAndOrDQLStatement($queryBuilder, $andORFilters, $fields, $iteration)) instanceof Orx) {
                    $queryBuilder->andWhere($ANDorStatements);
                }
            }
        }

        // .. OR (field1 ... AND field2 ...)
        if (array_key_exists(SearchFilter::OR, $whereFilters)) {
            $iteration = 0;

            /** @var array<string, TWhere[]> $orFilters */
            foreach ($whereFilters[SearchFilter::OR] as $orFilters) {
                ++$iteration;

                if (($ORStatements = self::getOrDQLStatement($queryBuilder, $orFilters, $fields, $iteration)) instanceof Andx) {
                    $queryBuilder->orWhere($ORStatements);
                }
            }
        }
    }

    /**
     * @param array<string, TWhere[]> $andORFilters
     * @param array<string, string>   $fields
     */
    private static function getAndOrDQLStatement(QueryBuilder $queryBuilder, array $andORFilters, array $fields, int $iteration): ?Orx
    {
        $ANDorStatements = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($andORFilters[$searchKey])) {
                continue;
            }

            $ANDorStatements ??= $queryBuilder->expr()->orX();

            foreach ($andORFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('andor%d_%s_i%d', $iteration, $searchKey, $index);
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

                    $ANDorStatements->add($orStatements);
                } else {
                    $ANDorStatements->add(
                        $queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey)
                    );

                    if (self::NULL_VALUE !== $_value) {
                        $queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        return $ANDorStatements;
    }

    /**
     * @param array<string, TWhere[]> $orFilters
     * @param array<string, string>   $fields
     */
    private static function getOrDQLStatement(QueryBuilder $queryBuilder, array $orFilters, array $fields, int $iteration): ?Andx
    {
        $ORStatements = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($orFilters[$searchKey])) {
                continue;
            }

            $ORStatements ??= $queryBuilder->expr()->andX();

            foreach ($orFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('or%d_%s_i%d', $iteration, $searchKey, $index);
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

                    $ORStatements->add($orStatements);
                } else {
                    $ORStatements->add(
                        $queryBuilder->expr()->{$expFn}($field, ':' . $_searchKey)
                    );

                    if (self::NULL_VALUE !== $_value) {
                        $queryBuilder->setParameter($_searchKey, $_value);
                    }
                }
            }
        }

        return $ORStatements;
    }

    /**
     * @param array<string, TWhere[]> $andORFilters
     * @param array<string, string>   $fields
     */
    private static function getAndOrDBALStatement(QueryBuilderDBAL $queryBuilderDBAL, array $andORFilters, array $fields, int $iteration): ?CompositeExpression
    {
        $ANDorStatements = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($andORFilters[$searchKey])) {
                continue;
            }

            $ANDorStatements ??= $queryBuilderDBAL->expr()->or('1=0');

            foreach ($andORFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('andor%d_%s_i%d', $iteration, $searchKey, $index);
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

                    $ANDorStatements = $ANDorStatements->with($orStatements);
                } else {
                    $ANDorStatements = $ANDorStatements->with(
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

        return $ANDorStatements;
    }

    /**
     * @param array<string, TWhere[]> $orFilters
     * @param array<string, string>   $fields
     */
    private static function getOrDBALStatement(QueryBuilderDBAL $queryBuilderDBAL, array $orFilters, array $fields, int $iteration): ?CompositeExpression
    {
        $ORStatements = null;
        foreach ($fields as $searchKey => $field) {
            if (!isset($orFilters[$searchKey])) {
                continue;
            }

            $ORStatements ??= $queryBuilderDBAL->expr()->and('1=1');

            foreach ($orFilters[$searchKey] as $index => $criteria) {
                $_searchKey = sprintf('or%d_%s_i%d', $iteration, $searchKey, $index);
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

                    $ORStatements = $ORStatements->with($orStatements);
                } else {
                    $ORStatements = $ORStatements->with(
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

        return $ORStatements;
    }
}
