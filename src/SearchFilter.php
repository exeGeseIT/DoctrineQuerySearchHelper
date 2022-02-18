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
    
    
    /**
     * depend 
     * ...WHERE field =
     * @param string $searchKey
     * @return string
     */
    public static function equal(string $searchKey): string
    {
        return self::EQUAL . $searchKey;
    }
    public static function notEqual(string $searchKey): string
    {
        return self::NOT_EQUAL . $searchKey;
    }
    public static function like(string $searchKey): string
    {
        return self::LIKE . $searchKey;
    }
    public static function notLike(string $searchKey): string
    {
        return self::NOT_LIKE . $searchKey;
    }
    public static function null(string $searchKey): string
    {
        return self::NULL . $searchKey;
    }
    public static function notNull(string $searchKey): string
    {
        return self::NOT_NULL . $searchKey;
    }
    public static function greater(string $searchKey): string
    {
        return self::GREATER . $searchKey;
    }
    public static function greaterOrEqual(string $searchKey): string
    {
        return self::GREATER_OR_EQUAL . $searchKey;
    }
    public static function lower(string $searchKey): string
    {
        return self::LOWER . $searchKey;
    }
    public static function lowerOrEqual(string $searchKey): string
    {
        return self::LOWER_OR_EQUAL . $searchKey;
    }
    
    
    
    private static $alias = [
        self::EQUAL => [ '==' ],
        self::NOT_EQUAL => [ '!=' ],
    ];
    
    public static function normalize(string $filter): string
    {
        $_filter = $filter;
        foreach (self::$alias as $normalizedFilter => $aliases) {
            if (in_array($filter, $aliases)) {
                $filter = $normalizedFilter;
                break;
            }
        }
        return $_filter;
    }
}
