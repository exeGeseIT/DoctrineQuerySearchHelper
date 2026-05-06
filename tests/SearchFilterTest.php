<?php

declare(strict_types=1);

namespace ExeGeseIT\Test;

use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use PHPUnit\Framework\TestCase;

final class SearchFilterTest extends TestCase
{
    public function testItBuildsEqualFilter(): void
    {
        self::assertSame('=name', SearchFilter::equal('name', false));
    }

    public function testItBuildsLikeFilter(): void
    {
        self::assertSame('%name', SearchFilter::like('name', false));
    }

    public function testItBuildsNotLikeStrictFilter(): void
    {
        self::assertSame('!%=name', SearchFilter::notLikeStrict('name', false));
    }

    public function testItDecodesEqualSearchFilter(): void
    {
        self::assertSame(
            [
                'key' => 'name',
                'filter' => SearchFilter::EQUAL,
            ],
            SearchFilter::decodeSearchfilter('=name')
        );
    }

    public function testItDecodesAliasEqualFilter(): void
    {
        self::assertSame(
            [
                'key' => 'name',
                'filter' => SearchFilter::EQUAL,
            ],
            SearchFilter::decodeSearchfilter('==name')
        );
    }

    public function testItDecodesAliasCompositeOrFilter(): void
    {
        $decoded = SearchFilter::decodeSearchfilter('||token');

        self::assertSame('token', $decoded['key']);
        self::assertSame(SearchFilter::COMPOSITE_OR, $decoded['filter']);
    }

    public function testItDetectsCompositeFilters(): void
    {
        self::assertTrue(SearchFilter::isCompositeFilter(SearchFilter::COMPOSITE_AND));
        self::assertTrue(SearchFilter::isCompositeFilter(SearchFilter::COMPOSITE_OR));
        self::assertTrue(SearchFilter::isCompositeFilter(SearchFilter::COMPOSITE_AND_OR));
        self::assertFalse(SearchFilter::isCompositeFilter(SearchFilter::EQUAL));
    }

    public function testItReturnsFilteredKey(): void
    {
        $searchParameters = [
            '=name~abc' => 'John',
            '%email~def' => 'john@example.com',
        ];

        self::assertSame('=name~abc', SearchFilter::getFilteredKey('name', $searchParameters));
    }

    public function testItReturnsFilteredKeyValue(): void
    {
        $searchParameters = [
            '=name~abc' => 'John',
            '%email~def' => 'john@example.com',
        ];

        self::assertSame('John', SearchFilter::getFilteredKeyValue('name', $searchParameters));
    }
}