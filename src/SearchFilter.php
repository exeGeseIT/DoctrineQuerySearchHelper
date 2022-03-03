<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

/**
 * Description of SearchFilter
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
class SearchFilter
{
    const EQUAL = '=';
    const NOT_EQUAL = '!';
    const LIKE = '%';
    const NOT_LIKE = '!%';
    const NULL = '_';
    const NOT_NULL = '!_';
    const GREATER = '>';
    const GREATER_OR_EQUAL = '>=';
    const LOWER = '<';
    const LOWER_OR_EQUAL = '<=';
    
    private static function getAlias(): iterable
    {
        yield self::EQUAL => [ '==' ];
        yield self::NOT_EQUAL => [ '!=' ];
    }
    
    public static function normalize(string $filter): string
    {
        $_filter = $filter;
        foreach (self::getAlias() as $normalizedFilter => $alias) {
            if (in_array($filter, $alias)) {
                $filter = $normalizedFilter;
                break;
            }
        }
        return $_filter;
    }
    
    
    /**
     * if isset($value)  --> ...WHERE searchKey = $value
     * if !isset($value) --> ...WHERE 1
     * 
     * @param string $searchKey
     * @return string
     */
    public static function filter(string $searchKey): string
    {
        return trim($searchKey);
    }
    
    /**
     * ...WHERE searchKey = $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function equal(string $searchKey): string
    {
        return self::EQUAL . trim($searchKey);
    }
    /**
     * ...WHERE searchKey <> $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function notEqual(string $searchKey): string
    {
        return self::NOT_EQUAL . trim($searchKey);
    }
    /**
     * ...WHERE searchKey LIKE $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function like(string $searchKey): string
    {
        return self::LIKE . trim($searchKey);
    }
    /**
     * ...WHERE searchKey NOT LIKE $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function notLike(string $searchKey): string
    {
        return self::NOT_LIKE . trim($searchKey);
    }
    /**
     * ...WHERE searchKey IS NULL
     * 
     * @param string $searchKey
     * @return string
     */
    public static function null(string $searchKey): string
    {
        return self::NULL . trim($searchKey);
    }
    /**
     * ...WHERE searchKey IS NOT NULL
     * 
     * @param string $searchKey
     * @return string
     */
    public static function notNull(string $searchKey): string
    {
        return self::NOT_NULL . trim($searchKey);
    }
    /**
     * ...WHERE searchKey > $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function greater(string $searchKey): string
    {
        return self::GREATER . trim($searchKey);
    }
    /**
     * ...WHERE searchKey >= $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function greaterOrEqual(string $searchKey): string
    {
        return self::GREATER_OR_EQUAL . trim($searchKey);
    }
    /**
     * ...WHERE searchKey < $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function lower(string $searchKey): string
    {
        return self::LOWER . trim($searchKey);
    }
    /**
     * ...WHERE searchKey <= $value
     * 
     * @param string $searchKey
     * @return string
     */
    public static function lowerOrEqual(string $searchKey): string
    {
        return self::LOWER_OR_EQUAL . trim($searchKey);
    }
    
}
