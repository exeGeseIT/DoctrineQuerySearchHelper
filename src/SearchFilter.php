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
    
    const OR = '|';
    const AND_OR = '&|';
    
    private static function getAlias(): iterable
    {
        yield self::EQUAL => [ '==' ];
        yield self::NOT_EQUAL => [ '!=' ];
        yield self::OR => [ '||' ];
    }
    
    private static function getToken(): string
    {
        return bin2hex(random_bytes(15));
    }
    
    private static function tokenize(bool $truly): string
    {
        return $truly ? '~' . self::getToken() : '';
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
     * 
     * @param string $searchfilter
     * @return array ['key' =>, 'filter =>]
     */
    public static function decodeSearchfilter(string $searchfilter): array
    {
        $matches = null;
        preg_match('/(?P<filter>[^[:alnum:]]+)?(?P<key>[[:alnum:]][^~]*)/i', $searchfilter, $matches);
        return [
            'key' => $matches['key'] ?? '',
            'filter' => $matches['filter'] ?? '',
        ];
    }
    
    
    /**
     * ...WHERE 1
     *    {{ AND ( .. OR .. OR ..) }}
     * 
     * @return string
     */
    public static function andOr(): string
    {
        return self::AND_OR . self::getToken();
    }
    /**
     * ...WHERE 1
     *    {{ OR ( .. AND .. AND ..) }}
     * 
     * @return string
     */
    public static function or(): string
    {
        return self::OR . self::getToken();
    }
    
    
    
    /**
     * isset($value)  ? => ...WHERE {{ searchKey = $value }}
     * !isset($value) ? => ...WHERE {{ 1 }}
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function filter(string $searchKey, bool $tokenize = true): string
    {
        return trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey = $value }}
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function equal(string $searchKey, bool $tokenize = true): string
    {
        return self::EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey <> $value }}
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function notEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey LIKE $value }}
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function like(string $searchKey, bool $tokenize = true): string
    {
        return self::LIKE . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey NOT LIKE $value
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function notLike(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_LIKE . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey IS NULL
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function null(string $searchKey, bool $tokenize = true): string
    {
        return self::NULL . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey IS NOT NULL
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function notNull(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_NULL . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey > $value
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function greater(string $searchKey, bool $tokenize = true): string
    {
        return self::GREATER . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey >= $value
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function greaterOrEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::GREATER_OR_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey < $value
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function lower(string $searchKey, bool $tokenize = true): string
    {
        return self::LOWER . trim($searchKey) . self::tokenize($tokenize);
    }
    /**
     * ...WHERE 1
     *    {{ AND searchKey <= $value }}
     * 
     * @param string $searchKey
     * @param bool $tokenize  if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     * @return string
     */
    public static function lowerOrEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::LOWER_OR_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }
    
}
