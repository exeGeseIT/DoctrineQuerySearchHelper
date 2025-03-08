<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Nette\Utils\Json;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-type TSearchvalue  scalar|array<int, int|string>
 * @phpstan-type TSearch       array<string, TSearchvalue|array<string, TSearchvalue>>
 * @phpstan-type TWhere        array{'expFn': string, 'value': TSearchvalue}
 * @phpstan-type TSort         array{'sort': string, 'direction': string}
 */
class SearchHelper
{
    final public const NULL_VALUE = '_NULL_';

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
     * @param string|int|float|array<int, string|int> $searched
     *
     * @return ($searched is iterable ? array<int, string> : string)
     */
    public static function sqlSearchString(mixed $searched, bool $strict = false): string|array
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
     * @param TSearch $search
     *
     * @return array<string, TWhere[]|array<string, array<string, TWhere[]>>>
     */
    public static function parseSearchParameters(array $search): array
    {
        $clauseFilters = [];
        foreach ($search as $ckey => $value) {
            $m = SearchFilter::decodeSearchfilter($ckey);

            $key = $m['key'];
            $filter = SearchFilter::normalize($m['filter']);

            $expFn = null;
            $fvalue = $value;

            if (in_array($filter, SearchFilter::COMPOSITE_FILTERS) && is_iterable($value)) {
                // @phpstan-ignore argument.type
                $clauseFilters[$ckey] = self::parseSearchParameters($value);
            } else {
                /** @var TSearchvalue $fvalue */
                [$expFn, $fvalue] = match ($filter) {
                    SearchFilter::EQUAL => [is_array($value) ? 'in' : 'eq', $value],
                    SearchFilter::NOT_EQUAL => [is_array($value) ? 'notIn' : 'neq', $value],
                    SearchFilter::NULL => ['isNull', self::NULL_VALUE],
                    SearchFilter::NOT_NULL => ['isNotNull', self::NULL_VALUE],
                    default => match ((bool) $value) {
                        false => [null, false],
                        default => match ($filter) {
                            SearchFilter::LOWER => ['lt', $value],
                            SearchFilter::LOWER_OR_EQUAL => ['lte', $value],
                            SearchFilter::GREATER => ['gt', $value],
                            SearchFilter::GREATER_OR_EQUAL => ['gte', $value],
                            // @phpstan-ignore argument.type
                            SearchFilter::LIKE => ['like', self::sqlSearchString($value)],
                            // @phpstan-ignore argument.type
                            SearchFilter::NOT_LIKE => ['notLike', self::sqlSearchString($value)],
                            // @phpstan-ignore argument.type
                            SearchFilter::LIKE_STRICK => ['like', self::sqlSearchString($value, strict: true)],
                            // @phpstan-ignore argument.type
                            SearchFilter::NOT_LIKE_STRICK => ['notLike', self::sqlSearchString($value, strict: true)],
                            default => [is_array($value) ? 'in' : 'eq', $value],
                        },
                    },
                };
            }

            if (null !== $expFn) {
                if (!array_key_exists($key, $clauseFilters)) {
                    $clauseFilters[$key] = [];
                }

                $clauseFilters[$key][] = [
                    'expFn' => $expFn,
                    'value' => is_bool($fvalue) ? (int) $fvalue : $fvalue,
                ];
            }
        }

        // @phpstan-ignore return.type
        return $clauseFilters;
    }
}
