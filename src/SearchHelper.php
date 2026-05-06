<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;
use Nette\Utils\Json;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @phpstan-type TSearchvalue float|int|string|list<float|int|string>
 * @phpstan-type TSearch array<string, bool|TSearchvalue|array<string, bool|TSearchvalue>>
 */
final class SearchHelper
{
    public const NULL_VALUE = '_NULL_';

    /**
     * SQL LIKE escape character.
     *
     * Must be a single character because SQL ESCAPE expects one character.
     */
    public const LIKE_ESCAPE_CHARACTER = '\\';

    /**
     * @var array<string, list<WhereCriteria>>|non-empty-array<string, array<string, list<WhereCriteria>>>
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
     * @return array<string, list<WhereCriteria>>|array<string, array<string, list<WhereCriteria>>>
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
    public static function sqlSearchString(
        mixed $searched,
        bool $strict = false,
        bool $lowercase = true,
        bool $trim = true,
    ): string|array {
        $strings = [];
        $stack = is_iterable($searched) ? $searched : [$searched];

        foreach ($stack as $searchedValue) {
            $value = (string) $searchedValue;

            if ($strict) {
                $strings[] = $value;
                continue;
            }

            if ($trim) {
                $value = trim($value);
            }

            if ($lowercase) {
                $value = \mb_strtolower($value);
            }

            $strings[] = sprintf('%%%s%%', self::escapeLikePattern($value));
        }

        return is_iterable($searched) ? $strings : $strings[0];
    }

    private static function escapeLikePattern(string $value): string
    {
        $escapeCharacter = self::LIKE_ESCAPE_CHARACTER;

        return strtr($value, [
            $escapeCharacter => $escapeCharacter.$escapeCharacter,
            '%' => $escapeCharacter.'%',
            '_' => $escapeCharacter.'_',
        ]);
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

            [$filterExprFn, $processedValue, $escapedLike] = array_pad($filterResult, 3, false);
            $this->addClauseFilter($key, $filterExprFn, $processedValue, $escapedLike);
        }
    }

    /**
     * @param bool|TSearchvalue|array<string, bool|TSearchvalue> $value
     *
     * @phpstan-assert-if-true array<string, bool|TSearchvalue> $value
     */
    private function isCompositeFilterValue(mixed $value): bool
    {
        return match (true) {
            !is_array($value) => false,
            array_is_list($value) => false,
            default => true,
        };
    }

    private function isFalsyValue(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value || 0 === $value || false === $value;
    }

    /**
     * @param TSearchvalue $value
     *
     * @return array{0: FilterExprFn, 1: TSearchvalue, 2?: bool}|null
     */
    private function processFilter(string $filter, mixed $value): ?array
    {
        if ($this->isFalsyValue($value) && SearchFilter::FILTER === $filter) {
            return null;
        }

        return match ($filter) {
            SearchFilter::NULL => [FilterExprFn::IsNull, self::NULL_VALUE],
            SearchFilter::NOT_NULL => [FilterExprFn::IsNotNull, self::NULL_VALUE],
            SearchFilter::LIKE => [FilterExprFn::Like, self::sqlSearchString($value), true],
            SearchFilter::NOT_LIKE => [FilterExprFn::NotLike, self::sqlSearchString($value), true],
            SearchFilter::LIKE_STRICT => [FilterExprFn::Like, self::sqlSearchString($value, true), false],
            SearchFilter::NOT_LIKE_STRICT => [FilterExprFn::NotLike, self::sqlSearchString($value, true), false],
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
    private function addClauseFilter(string $key, FilterExprFn $filterExprFn, mixed $value, bool $escapedLike = false): void
    {
        if (!isset($this->clauseFilters[$key])) {
            $this->clauseFilters[$key] = [];
        }

        $this->clauseFilters[$key][] = new WhereCriteria(
            filterExprFn: $filterExprFn,
            value: $value,
            escapedLike: $escapedLike,
        );
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
