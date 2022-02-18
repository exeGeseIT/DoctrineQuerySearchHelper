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
     * ...WHERE field = $value
     * @param string $searchKey
     * @return string
     */
    public static function equal(string $searchKey): string
    {
        return self::EQUAL . $searchKey;
    }
    /**
     * ...WHERE field <> $value
     * @param string $searchKey
     * @return string
     */
    public static function notEqual(string $searchKey): string
    {
        return self::NOT_EQUAL . $searchKey;
    }
    /**
     * ...WHERE field LIKE $value
     * @param string $searchKey
     * @return string
     */
    public static function like(string $searchKey): string
    {
        return self::LIKE . $searchKey;
    }
    /**
     * ...WHERE field NOT LIKE $value
     * @param string $searchKey
     * @return string
     */
    public static function notLike(string $searchKey): string
    {
        return self::NOT_LIKE . $searchKey;
    }
    /**
     * ...WHERE field IS NULL
     * @param string $searchKey
     * @return string
     */
    public static function null(string $searchKey): string
    {
        return self::NULL . $searchKey;
    }
    /**
     * ...WHERE field IS NOT NULL
     * @param string $searchKey
     * @return string
     */
    public static function notNull(string $searchKey): string
    {
        return self::NOT_NULL . $searchKey;
    }
    /**
     * ...WHERE field > $value
     * @param string $searchKey
     * @return string
     */
    public static function greater(string $searchKey): string
    {
        return self::GREATER . $searchKey;
    }
    /**
     * ...WHERE field >= $value
     * @param string $searchKey
     * @return string
     */
    public static function greaterOrEqual(string $searchKey): string
    {
        return self::GREATER_OR_EQUAL . $searchKey;
    }
    /**
     * ...WHERE field < $value
     * @param string $searchKey
     * @return string
     */
    public static function lower(string $searchKey): string
    {
        return self::LOWER . $searchKey;
    }
    /**
     * ...WHERE field <= $value
     * @param string $searchKey
     * @return string
     */
    public static function lowerOrEqual(string $searchKey): string
    {
        return self::LOWER_OR_EQUAL . $searchKey;
    }
    
}
