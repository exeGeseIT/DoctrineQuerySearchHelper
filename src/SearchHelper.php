<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use function ctype_print;
use function mb_strtolower;

/**
 * Description of SearchHelper
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
class SearchHelper
{
    private const NULL_VALUE = '_NULL_';
    
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
            if ( in_array($filter, [SearchFilter::OR, SearchFilter::AND_OR]) && is_iterable($value) ) {
                if ( !array_key_exists($filter, $clauseFilters) ){
                    $clauseFilters[ $filter ] = [];
                }
                $clauseFilters[ $filter ][] = self::parseSearchParameters($value);
                
            } elseif ( $filter === SearchFilter::EQUAL ) {
               $_expFn = is_array($value) ? 'in' : 'eq';

            } elseif ( $filter === SearchFilter::NOT_EQUAL ) {
               $_expFn = is_array($value) ? 'notIn' : 'neq';

            } elseif ( in_array($filter, [SearchFilter::NULL, SearchFilter::NOT_NULL]) ) {
               $_expFn = $filter === '_' ? 'isNull' : 'isNotNull';
               $value = self::NULL_VALUE;

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
    private static function setQbDQLSearchClause(QueryBuilder $qb, iterable $whereFilters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($whereFilters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $whereFilters[ $searchKey ]['_expFn'];
            $_value = $whereFilters[ $searchKey ]['value'];
            if ( !in_array($_expFn, ['in','notIn']) && is_array($_value) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $_value as $pattern ) {
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
                if ( self::NULL_VALUE !== $_value ) {
                   $qb->setParameter( $searchKey , $_value);
                }
            }
        }
        
        //.. AND (field1 ... OR field2 ...)
        if ( array_key_exists(SearchFilter::AND_OR, $whereFilters) )  {
            $iteration = 0;
            foreach( $whereFilters[ SearchFilter::AND_OR ] as $andORFilters ) {
                $iteration++;
                if( $ANDorStatements = self::getAndOrDQLStatement($qb, $andORFilters, $fields, $iteration) ) {
                    $qb->andWhere($ANDorStatements);
                }
            }
        }
        
        //.. OR (field1 ... AND field2 ...)
        if ( array_key_exists(SearchFilter::OR, $whereFilters) )  {
            $iteration = 0;
            foreach( $whereFilters[ SearchFilter::OR ] as $orFilters ) {
                $iteration++;
                if( $ORStatements = self::getOrDQLStatement($qb, $orFilters, $fields, $iteration) ) {
                    $qb->orWhere($ORStatements);
                }
            }
        }
    }
    
    private static function getAndOrDQLStatement(QueryBuilder $qb, iterable $andORFilters, iterable $fields, int $iteration): ?Orx
    {
        $ANDorStatements = null;
        foreach ($fields as $searchKey => $field) {
            if ( !isset($andORFilters[ $searchKey ]) ) {
                continue;
            }
            
            $ANDorStatements = $ANDorStatements ?? $qb->expr()->orX();
            
            $_expFn = $andORFilters[ $searchKey ]['_expFn'];
            $_value = $andORFilters[ $searchKey ]['value'];
            if ( !in_array($_expFn, ['in','notIn']) && is_array($_value) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $_value as $pattern ) {
                    $_searchKey = sprintf('andor%d_%s_%d', $iteration, $searchKey, $i++);
                    $orStatements->add(
                        $qb
                          ->setParameter( $_searchKey , $pattern)
                          ->expr()->$_expFn($field, ':'. $_searchKey )
                    );
                }
                $ANDorStatements->add($orStatements);
            } else {
                $_searchKey = sprintf('andor%d_%s', $iteration, $searchKey);
                $ANDorStatements->add(
                    $qb
                      ->expr()->$_expFn($field, ':'. $_searchKey )
                );
                
                if ( self::NULL_VALUE !== $_value ) {
                   $qb->setParameter( $_searchKey , $_value);
                }
            }
        }
        return $ANDorStatements;
    }
    
    private static function getOrDQLStatement(QueryBuilder $qb, iterable $orFilters, iterable $fields, int $iteration): ?Andx
    {
        $ORStatements = null;
        foreach ($fields as $searchKey => $field) {
            if ( !isset($orFilters[ $searchKey ]) ) {
                continue;
            }
            
            $ORStatements = $ORStatements ?? $qb->expr()->andX();
            
            $_expFn = $orFilters[ $searchKey ]['_expFn'];
            $_value = $orFilters[ $searchKey ]['value'];
            if ( !in_array($_expFn, ['in','notIn']) && is_array($_value) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $_value as $pattern ) {
                    $_searchKey = sprintf('or%d_%s_%d', $iteration, $searchKey, $i++);
                    $orStatements->add(
                        $qb
                          ->setParameter( $_searchKey , $pattern)
                          ->expr()->$_expFn($field, ':'. $_searchKey )
                    );
                }
                $ORStatements->add($orStatements);
            } else {
                $_searchKey = sprintf('or%d_%s', $iteration, $searchKey);
                $ORStatements->add(
                    $qb
                      ->expr()->$_expFn($field, ':'. $_searchKey )
                );
                
                if ( self::NULL_VALUE !== $_value ) {
                   $qb->setParameter( $_searchKey , $orFilters[ $searchKey ]['value']);
                }
            }
        }
        return $ORStatements;
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
    public static function setQbDBALSearchClause(QueryBuilderDBAL $qb, iterable $whereFilters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($whereFilters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $whereFilters[ $searchKey ]['_expFn'];
            $_value = $whereFilters[ $searchKey ]['value'];
            if ( is_array($_value) ) {
                $i = 0;
                $orStatements = $qb->expr()->or();
                foreach ( $_value as $pattern ) {
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
                if ( self::NULL_VALUE !== $_value ) {
                    $_typeValue = null;
                    if ( is_array($_value) ) {
                        $_typeValue = is_int( $_value[0] ) ? Connection::PARAM_INT_ARRAY : Connection::PARAM_STR_ARRAY;
                    }
                    $qb->setParameter( $searchKey , $_value, $_typeValue);
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
