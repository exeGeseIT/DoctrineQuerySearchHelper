<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
final class SearchFilter
{
    public const FILTER = '?';
    public const EQUAL = '=';
    public const NOT_EQUAL = '!';
    public const LIKE = '%';
    public const NOT_LIKE = '!%';
    public const LIKE_STRICT = '%=';
    public const NOT_LIKE_STRICT = '!%=';
    public const NULL = '_';
    public const NOT_NULL = '!_';
    public const GREATER = '>';
    public const GREATER_OR_EQUAL = '>=';
    public const LOWER = '<';
    public const LOWER_OR_EQUAL = '<=';
    public const COMPOSITE_OR = '|';
    public const COMPOSITE_AND_OR = '&|';
    public const COMPOSITE_AND = '&';

    public static function isCompositeFilter(string $filter): bool
    {
        return in_array($filter, [
            self::COMPOSITE_AND,
            self::COMPOSITE_OR,
            self::COMPOSITE_AND_OR,
        ]);
    }

    private static function normalize(string $filter): string
    {
        $_filter = $filter;
        foreach (self::getAlias() as $normalizedFilter => $alias) {
            if (in_array($filter, $alias)) {
                $_filter = $normalizedFilter;
                break;
            }
        }

        return $_filter;
    }

    /**
     * @return array{key: string, filter: string}
     */
    public static function decodeSearchfilter(string $searchfilter): array
    {
        $matches = [];
        preg_match('/(?P<filter>[^[:alnum:]]+)?(?P<key>[[:alnum:]][^~]*)/i', $searchfilter, $matches);

        return [
            'key' => $matches['key'] ?? '',
            'filter' => self::normalize($matches['filter'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $searchParameters
     */
    public static function hasFilteredKey(string $searchKey, array $searchParameters): bool
    {
        return null !== self::getFilteredKey($searchKey, $searchParameters);
    }

    /**
     * Return <filteredkey> searchKey or null.
     *
     * @param array<string, array<int|string>|bool|int|string> $searchParameters
     */
    public static function getFilteredKey(string $searchKey, array $searchParameters): ?string
    {
        $hash = sprintf(':%s', implode(':', array_keys($searchParameters)));
        $pattern = '/:(?P<filteredkey>(?:[^[:alnum:]])?' . $searchKey . '~[[:alnum:]]+)/i';
        preg_match($pattern, $hash, $matches);

        return $matches['filteredkey'] ?? null;
    }

    /**
     * Return <searchKey> $searchParameters value or null.
     *
     * @param array<string, array<int|string>|bool|int|string> $searchParameters
     *
     * @return array<int|string>|bool|int|string|null
     */
    public static function getFilteredKeyValue(string $searchKey, array $searchParameters): string|int|array|bool|null
    {
        $filteredKey = self::getFilteredKey($searchKey, $searchParameters);

        return null === $filteredKey || '' === $filteredKey || '0' === $filteredKey ? null : $searchParameters[$filteredKey];
    }

    /**
     * ...WHERE 1
     *    {{ AND ( .. OR .. OR ..) }}.
     */
    public static function andOr(): string
    {
        return self::COMPOSITE_AND_OR . self::getToken();
    }

    /**
     * ...WHERE 1
     *    {{ AND ( .. AND .. AND ..) }}.
     */
    public static function and(): string
    {
        return self::COMPOSITE_AND . self::getToken();
    }

    /**
     * ...WHERE 1
     *    {{ OR ( .. AND .. AND ..) }}.
     */
    public static function or(): string
    {
        return self::COMPOSITE_OR . self::getToken();
    }

    /**
     * !empty($value)  ? => ...WHERE {{ searchKey = $value }}
     * empty($value) ? => ...WHERE {{ 1 }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function filter(string $searchKey, bool $tokenize = true): string
    {
        return self::FILTER . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey = $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function equal(string $searchKey, bool $tokenize = true): string
    {
        return self::EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey <> $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function notEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey LIKE $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function like(string $searchKey, bool $tokenize = true): string
    {
        return self::LIKE . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey NOT LIKE $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function notLike(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_LIKE . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * Differs from SearchFilter::like() in that "$searchKey" is taken as is.
     *   i.e.: the characters '%' and '_' are neither appended nor escaped.
     *
     * ...WHERE 1
     *    {{ AND searchKey LIKE $value }}
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function likeStrict(string $searchKey, bool $tokenize = true): string
    {
        return self::LIKE_STRICT . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * Differs from SearchFilter::notLike() in that "$searchKey" is taken as is.
     *   i.e.: the characters '%' and '_' are neither appended nor escaped.
     *
     * ...WHERE 1
     *    {{ AND searchKey NOT LIKE $value
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function notLikeStrict(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_LIKE_STRICT . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey IS NULL }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function null(string $searchKey, bool $tokenize = true): string
    {
        return self::NULL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey IS NOT NULL }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function notNull(string $searchKey, bool $tokenize = true): string
    {
        return self::NOT_NULL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey > $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function greater(string $searchKey, bool $tokenize = true): string
    {
        return self::GREATER . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey >= $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function greaterOrEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::GREATER_OR_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey < $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function lower(string $searchKey, bool $tokenize = true): string
    {
        return self::LOWER . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * ...WHERE 1
     *    {{ AND searchKey <= $value }}.
     *
     * @param bool $tokenize if TRUE, a random hash is added to the returned string to ensure its uniqueness.
     */
    public static function lowerOrEqual(string $searchKey, bool $tokenize = true): string
    {
        return self::LOWER_OR_EQUAL . trim($searchKey) . self::tokenize($tokenize);
    }

    /**
     * @return iterable<string, string[]>
     */
    private static function getAlias(): iterable
    {
        yield self::EQUAL => ['=='];
        yield self::NOT_EQUAL => ['!='];
        yield self::COMPOSITE_OR => ['||'];
    }

    private static function getToken(): string
    {
        return bin2hex(random_bytes(15));
    }

    private static function tokenize(bool $truly): string
    {
        return $truly ? '~' . self::getToken() : '';
    }
}
