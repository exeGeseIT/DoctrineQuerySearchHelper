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
     * @var array<string, list<TWhere>>|non-empty-array<string, array<string, list<TWhere>>>
     */
    private array $clauseFilters = [];

    /**
     * @param TSearch $search
     */
    public function __construct(array $search)
    {
        $this->parseSearchParameters($search);
    }

    /**
     * @return array<string, list<TWhere>>|array<string, array<string, list<TWhere>>>
     */
    public function getClauseFilters(): array
    {
        return $this->clauseFilters;
    }

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
        $helper = new self($search);

        return Json::encode(
            value: $helper->getClauseFilters(),
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
     */
    private function parseSearchParameters(array $search): void
    {
        foreach ($search as $searchfilter => $value) {
            if ($this->isCompositeFilterValue($value)) {
                $this->addCompositeClauseFilter($searchfilter, $value);
                continue;
            }

            $demuxedFilter = SearchFilter::decodeSearchfilter($searchfilter);
            $filter = $demuxedFilter['filter'];
            $key = $demuxedFilter['key'];

            if (is_bool($value)) {
                $value = (int) $value;
            }

            $filterResult = $this->processFilter($filter, $value);

            if (null === $filterResult) {
                continue;
            }

            [$expFn, $processedValue] = $filterResult;
            $this->addClauseFilter($key, $expFn, $processedValue);
        }
    }

    /**
     * @param bool|TSearchvalue|array<string, bool|TSearchvalue> $value
     *
     * @phpstan-assert-if-true =array<string, bool|TSearchvalue> $value
     */
    private function isCompositeFilterValue(mixed $value): bool
    {
        return match (true) {
            !is_array($value) => false,
            array_is_list($value) => false,
            default => true,
        };
    }

    private function isEmptyValue(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value || 0 === $value || false === $value;
    }

    /**
     * @param TSearchvalue $value
     *
     * @return array{0: FilterExprFn, 1: TSearchvalue}|null
     */
    private function processFilter(string $filter, mixed $value): ?array
    {
        if ($this->isEmptyValue($value) && (SearchFilter::FILTER === $filter)) {
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
     * @param TSearchvalue $value
     */
    private function addClauseFilter(string $key, FilterExprFn $filterExprFn, mixed $value): void
    {
        if (!isset($this->clauseFilters[$key])) {
            $this->clauseFilters[$key] = [];
        }

        $this->clauseFilters[$key][] = [
            'expFn' => $filterExprFn,
            'value' => $value,
        ];
    }

    /**
     * @param array<string, bool|TSearchvalue> $value
     */
    private function addCompositeClauseFilter(string $searchfilter, mixed $value): void
    {
        $demuxedFilter = SearchFilter::decodeSearchfilter($searchfilter);
        $filter = $demuxedFilter['filter'];

        if (SearchFilter::isCompositeFilter($filter)) {
            $helper = new self($value);
            $this->clauseFilters[$searchfilter] = $helper->getClauseFilters();
        }
    }
}
