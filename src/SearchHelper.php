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
    public static function sqlSearchString($searched): string
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


    public static function parseSearchParameters(array $search): array
    {
        $_search = [];

        foreach ( $search as $ckey => $value ) {

            $c = substr($ckey,0,2);
            $key = substr($ckey,2);

            if ( !in_array($c, ['<=', '>=', '==', '!=', '!%', '!_']) ) {
                $c = null;
                $key = $ckey;
            }

            if ( null === $c ) {
                $c = substr($ckey,0,1);
                $key = substr($ckey,1);

                if ( !in_array($c, ['!', '=', '<', '>', '%', '_']) ) {
                    $c = null;
                    $key = $ckey;
                }
            }

            $_expFn = null;
            if ( $c === '=' || $c === '==' ) {
               $_expFn = is_array($value) ? 'in' : 'eq';

            } elseif ( $c === '!=' ) {
               $_expFn = is_array($value) ? 'notIn' : 'neq';

            } elseif ( in_array($c, ['_','!_']) ) {
               $_expFn = $c === '_' ? 'isNull' : 'isNotNull';
               $value = '_NULL_';

            } elseif ( !empty($value) ) {
                switch ( $c ) {

                    case '!':
                        $_expFn = is_array($value) ? 'notIn' : 'neq';
                        break;

                    case '<':
                        $_expFn = 'lt';
                        break;

                    case '<=':
                        $_expFn = 'lte';
                        break;

                    case '>':
                        $_expFn = 'gt';
                        break;

                    case '>=':
                        $_expFn = 'gte';
                        break;

                    case '%':
                        $_expFn = 'like';
                        $value = self::sqlSearchString($value);
                        break;

                    case '!%':
                        $_expFn = 'notLike';
                        $value = self::sqlSearchString($value);
                        break;

                    default:
                        $_expFn = is_array($value) ? 'in' : 'eq';
                        break;
                }
            }

            if ( null !== $_expFn ) {
                $_search[ $key ] = [
                    'value' => $value,
                    '_expFn' => $_expFn,
                ];
            }
        }

        return $_search;
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
            self::setQbDQLSearchClause($qb, $search, $fields);
        } elseif ( $qb instanceof QueryBuilderDBAL ) {
            self::setQbDBALSearchClause($qb, $search, $fields);
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
    private static function setQbDQLSearchClause(QueryBuilder $qb, iterable $searchParameters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($searchParameters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $searchParameters[ $searchKey ]['_expFn'];
            if ( is_array( $searchParameters[ $searchKey ]['value'] ) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $searchParameters[ $searchKey ]['value'] as $pattern ) {
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
                if ( '_NULL_' !== $searchParameters[ $searchKey ]['value'] ) {
                   $qb->setParameter( $searchKey , $searchParameters[ $searchKey ]['value']);
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
    public static function setQbDBALSearchClause(QueryBuilderDBAL $qb, iterable $searchParameters, iterable $fields)
    {
        foreach ($fields as $searchKey => $field) {
            if ( !isset($searchParameters[ $searchKey ]) ) {
                continue;
            }
            
            $_expFn = $searchParameters[ $searchKey ]['_expFn'];
            if ( is_array( $searchParameters[ $searchKey ]['value'] ) ) {
                $i = 0;
                $orStatements = $qb->expr()->orX();
                foreach ( $searchParameters[ $searchKey ]['value'] as $pattern ) {
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
                if ( '_NULL_' !== $searchParameters[ $searchKey ]['value'] ) {
                    $_typeValue = null;
                    if ( is_array( $searchParameters[ $searchKey ]['value'] ) ) {
                        $_typeValue = is_int( $searchParameters[ $searchKey ]['value'][0] ) ? Connection::PARAM_INT_ARRAY : Connection::PARAM_STR_ARRAY;
                    }
                    $qb->setParameter( $searchKey , $searchParameters[ $searchKey ]['value'], $_typeValue);
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
