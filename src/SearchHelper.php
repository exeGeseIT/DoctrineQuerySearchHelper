<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Nette\Utils\Json;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-type TSearchvalue float|int|string|list<float|int|string>
 * @phpstan-type TSearch array<string, bool|TSearchvalue|array<string, bool|TSearchvalue>>
 * @phpstan-type TWhere  array{'expFn': FilterExprFn, 'value': TSearchvalue}
 * @phpstan-type TSort array{'sort': string, 'direction': string}
 */
final class SearchHelper
{
    public const NULL_VALUE = '_NULL_';

    /**
     * @param TSearch $search
     */
    public static function dumpParsedSearchParameters(
        array $search,
        bool $pretty = false,
        bool $asciiSafe = false,
        bool $htmlSafe = false,
        bool $forceObjects = false,
    ): string {
        return Json::encode(
            value: self::parseSearchParameters($search),
            pretty: $pretty,
            asciiSafe: $asciiSafe,
            htmlSafe: $htmlSafe,
            forceObjects: $forceObjects,
        );
    }

    /**
     * @param TSearchvalue $searched
     *
     * @return ($searched is iterable ? list<string> : string)
     */
    public static function sqlSearchString(mixed $searched, bool $strict = false): string|array
    {
        $strings = [];
        $stack = is_iterable($searched) ? $searched : [$searched];
        foreach ($stack as $searchedValue) {
            $strings[] = match ($strict) {
                true => (string) $searchedValue,
                default => sprintf('%%%s%%', str_replace(['%', '_'], ['\%', '\_'], trim(\mb_strtolower((string) $searchedValue)))),
            };
        }

        return is_iterable($searched) ? $strings : $strings[0];
    }

    /**
     * @param TSearch $search
     *
     * @return array<string, list<TWhere>>|array<string, array<string, list<TWhere>>>
     */
    public static function parseSearchParameters(array $search): array
    {
        $clauseFilters = [];
        foreach ($search as $searchfilter => $value) {
            if (self::isCompositeFilterValue($value)) {
                self::addCompositeClauseFilter($clauseFilters, $searchfilter, $value);
                continue;
            }

            $demuxedFilter = SearchFilter::decodeSearchfilter($searchfilter);
            $filter = $demuxedFilter['filter'];
            $key = $demuxedFilter['key'];

            if (is_bool($value)) {
                $value = (int) $value;
            }

            $filterResult = self::processFilter($filter, $value);

            if (null === $filterResult) {
                continue;
            }

            [$expFn, $processedValue] = $filterResult;
            self::addClauseFilter($clauseFilters, $key, $expFn, $processedValue);
        }

        return $clauseFilters;
    }

    /**
     * @param bool|TSearchvalue|array<string, bool|TSearchvalue> $value
     *
     * @phpstan-assert-if-true =array<string, bool|TSearchvalue> $value
     */
    private static function isCompositeFilterValue(mixed $value): bool
    {
        return match (true) {
            !is_array($value) => false,
            array_is_list($value) => false,
            default => true,
        };
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value || false === $value;
    }

    /**
     * @param TSearchvalue $value
     *
     * @return array{0: FilterExprFn, 1: TSearchvalue}|null
     */
    private static function processFilter(string $filter, mixed $value): ?array
    {
        if (self::isEmptyValue($value) && !SearchFilter::isLaxeFilter($filter)) {
            return null;
        }

        return match ($filter) {
            SearchFilter::NULL => [FilterExprFn::IsNull, self::NULL_VALUE],
            SearchFilter::NOT_NULL => [FilterExprFn::IsNotNull, self::NULL_VALUE],
            SearchFilter::LIKE => [FilterExprFn::Like, self::sqlSearchString($value)],
            SearchFilter::NOT_LIKE => [FilterExprFn::NotLike, self::sqlSearchString($value)],
            SearchFilter::LIKE_STRICT => [FilterExprFn::Like, self::sqlSearchString($value, true)],
            SearchFilter::NOT_LIKE_STRICT => [FilterExprFn::NotLike, self::sqlSearchString($value, true)],
            SearchFilter::LOWER => [FilterExprFn::Lower, $value],
            SearchFilter::LOWER_OR_EQUAL => [FilterExprFn::LowerOrEqual, $value],
            SearchFilter::GREATER => [FilterExprFn::Greater, $value],
            SearchFilter::GREATER_OR_EQUAL => [FilterExprFn::GreaterOrEqual, $value],
            SearchFilter::NOT_EQUAL => [is_array($value) ? FilterExprFn::NotIn : FilterExprFn::NotEqual, $value],
            default => [is_array($value) ? FilterExprFn::In : FilterExprFn::Equal, $value],
        };
    }

    /**
     * @param array<string, list<TWhere>|array<string, list<TWhere>>> $clauseFilters
     * @param TSearchvalue                                            $value
     */
    private static function addClauseFilter(array &$clauseFilters, string $key, FilterExprFn $filterExprFn, mixed $value): void
    {
        if (!isset($clauseFilters[$key])) {
            $clauseFilters[$key] = [];
        }

        $clauseFilters[$key][] = [
            'expFn' => $filterExprFn,
            'value' => $value,
        ];
    }

    /**
     * @param array<string, list<TWhere>|array<string, list<TWhere>>> $clauseFilters
     * @param array<string, bool|TSearchvalue>                        $value
     */
    private static function addCompositeClauseFilter(array &$clauseFilters, string $searchfilter, mixed $value): void
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($searchfilter);
        $filter = $demuxedFilter['filter'];

        if (SearchFilter::isCompositeFilter($filter)) {
            $clauseFilters[$searchfilter] = self::parseSearchParameters($value);
        }
    }
}
