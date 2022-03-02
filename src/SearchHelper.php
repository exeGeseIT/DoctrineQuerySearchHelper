<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use function mb_strtolower;

/**
 * Description of SearchHelper
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
class SearchHelper
{
    /**
     *
     * @param string $searched
     * @return string|array
     */
    public static function sqlSearchString($searched)
    {
        if (is_iterable($searched) ) {
            $_s = [];
            foreach ($searched as $searchedValue ) {
                $_s[] = ctype_print($searchedValue) ? '%' . str_replace(['%', '_'], ['\%', '\_'], trim(mb_strtolower($searchedValue))) . '%' : $searchedValue;
            }
            return $_s;
        }

        return  ctype_print($searched) ? '%' . str_replace(['%', '_'], ['\%', '\_'], trim(mb_strtolower($searched))) . '%' : $searched;
    }


    /**
     * 
     * @param array $search
     * @return array
     */
    private static function parseSearchParameters(array $search): array
    {
        $clauseFilters = [];

        foreach ( $search as $ckey => $value ) {
            
            $matches = null;
            preg_match('/(?P<filter>[^[:alnum:]]+)?(?P<key>[[:alnum:]].*)/i', $ckey, $matches);

            $filter = SearchFilter::normalize($matches['filter']);
            $key = $matches['key'];

            $_expFn = null;
            if ( $filter === SearchFilter::EQUAL ) {
               $_expFn = is_array($value) ? 'in' : 'eq';

            } elseif ( $filter === SearchFilter::NOT_EQUAL ) {
               $_expFn = is_array($value) ? 'notIn' : 'neq';

            } elseif ( in_array($filter, [SearchFilter::NULL, SearchFilter::NOT_NULL]) ) {
               $_expFn = $filter === '_' ? 'isNull' : 'isNotNull';
               $value = '_NULL_';

            } elseif ( !empty($value) ) {
                switch ( $filter ) {

                    case SearchFilter::LOWER:
                        $_expFn = 'lt';
                        break;

                    case SearchFilter::LOWER_OR_EQUAL:
                        $_expFn = 'lte';
                        break;

                    case SearchFilter::GREATER:
                        $_expFn = 'gt';
                        break;

                    case SearchFilter::GREATER_OR_EQUAL:
                        $_expFn = 'gte';
                        break;

                    case SearchFilter::LIKE:
                        $_expFn = 'like';
                        $value = self::sqlSearchString($value);
                        break;

                    case SearchFilter::NOT_LIKE:
                        $_expFn = 'notLike';
                        $value = self::sqlSearchString($value);
                        break;

                    default:
                        $_expFn = is_array($value) ? 'in' : 'eq';
                        break;
                }
            }

            if ( null !== $_expFn ) {
                $clauseFilters[ $key ] = [
                    'value' => $value,
                    '_expFn' => $_expFn,
                ];
            }
        }

        return $clauseFilters;
    }



    /**
     * 
     * @param \Doctrine\ORM\QueryBuilder | \Doctrine\DBAL\Query\QueryBuilder $qb
     * @param iterable $search
     * @param iterable $fields
     * @throws InvalidArgumentException
     */
    public static function setQbSearchClause($qb, iterable $search, iterable $fields = [])
    {
        if ( $qb instanceof QueryBuilder ) {
            self::setQbDQLSearchClause($qb, self::parseSearchParameters($search), $fields);
        } elseif ( $qb instanceof QueryBuilderDBAL ) {
            self::setQbDBALSearchClause($qb, self::parseSearchParameters($search), $fields);
        } else {
            throw new InvalidArgumentException( sprintf('$qb should be instance of %s or %s class', QueryBuilder::class, QueryBuilderDBAL::class) );
        }
    }

    /**
     *
     * @param \Doctrine\ORM\QueryBuilder | \Doctrine\DBAL\Query\QueryBuilder $qb
     * @param string|null $paginatorSort
     * @throws InvalidArgumentException
     */
    public static function initializeQbPaginatorOrderby($qb, ?string $paginatorSort )
    {
        if ( $qb instanceof QueryBuilder ) {
            self::initializeQbPaginatorOrderbyDQL($qb, $paginatorSort ?? '');
        } elseif ( $qb instanceof QueryBuilderDBAL ) {
            self::initializeQbPaginatorOrderbyDBAL($qb, $paginatorSort ?? '');
        } else {
            throw new InvalidArgumentException( sprintf('$qb should be instance of %s or %s class', QueryBuilder::class, QueryBuilderDBAL::class) );
        }
    }




    /** ******************
     *
     *  DQL Helpers
     *
     ****************** */
    private static function setQbDQLSearchClause(QueryBuilder $qb, iterable $clauseFilters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($clauseFilters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $clauseFilters[ $searchKey ]['_expFn'];
            if ( 'in' !== $_expFn && is_array( $clauseFilters[ $searchKey ]['value'] ) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $clauseFilters[ $searchKey ]['value'] as $pattern ) {
                    $_searchKey = sprintf('%s_%d',$searchKey,$i++);
                    $orStatements->add(
                        $qb
                          ->setParameter( $_searchKey , $pattern)
                          ->expr()->$_expFn($field, ':'. $_searchKey )
                    );
                }
                $qb->andWhere($orStatements);
            } else {
                $qb->andWhere($qb->expr()->$_expFn($field, ':'. $searchKey ));
                if ( '_NULL_' !== $clauseFilters[ $searchKey ]['value'] ) {
                   $qb->setParameter( $searchKey , $clauseFilters[ $searchKey ]['value']);
                }
            }
                
        }
    }
    
    public static function initializeQbPaginatorOrderbyDQL(QueryBuilder $qb, string $paginatorSort )
    {
        if ( !empty($paginatorSort) ) {
            $_initial_order = $qb->getDQLPart('orderBy');
            $qb->add('orderBy', str_replace('+',',',$paginatorSort));
            foreach ($_initial_order as $sort) {
                $qb->addOrderBy($sort);
            }
        }
    }



    /** ******************
     *
     *  DBAL Helpers
     *
     ****************** */
    public static function setQbDBALSearchClause(QueryBuilderDBAL $qb, iterable $clauseFilters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($clauseFilters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $clauseFilters[ $searchKey ]['_expFn'];
            if ( is_array( $clauseFilters[ $searchKey ]['value'] ) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $clauseFilters[ $searchKey ]['value'] as $pattern ) {
                    $_searchKey = sprintf('%s_%d',$searchKey,$i++);
                    $orStatements->add(
                        $qb
                          ->setParameter( $_searchKey , $pattern)
                          ->expr()->$_expFn($field, ':'. $_searchKey )
                    );
                }
                $qb->andWhere($orStatements);
            } else {
                $qb->andWhere($qb->expr()->$_expFn($field, ':'. $searchKey ));
                if ( '_NULL_' !== $clauseFilters[ $searchKey ]['value'] ) {
                    $_typeValue = null;
                    if ( is_array( $clauseFilters[ $searchKey ]['value'] ) ) {
                        $_typeValue = is_int( $clauseFilters[ $searchKey ]['value'][0] ) ? Connection::PARAM_INT_ARRAY : Connection::PARAM_STR_ARRAY;
                    }
                    $qb->setParameter( $searchKey , $clauseFilters[ $searchKey ]['value'], $_typeValue);
                }
            }
        }
    }
    

    public static function initializeQbPaginatorOrderbyDBAL(QueryBuilderDBAL $qb, string $paginatorSort )
    {
        if ( !empty($paginatorSort) ) {
            $_initial_order = $qb->getQueryPart('orderBy');
            $qb->add('orderBy', str_replace('+',',',$paginatorSort));
            foreach ($_initial_order as $sort) {
                $qb->addOrderBy($sort);
            }
        }
    }

}
