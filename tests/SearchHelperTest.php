<?php

declare(strict_types=1);

namespace ExeGeseIT\Test;

use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use PHPUnit\Framework\TestCase;

final class SearchHelperTest extends TestCase
{
    public function testItEscapesSqlSearchString(): void
    {
        self::assertSame('%foo\\%bar\\_baz\\\\qux%', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux '));
    }

    public function testItEscapesSqlSearchStringWithoutLowercasing(): void
    {
        self::assertSame('%Foo\\%Bar\\_Baz\\\\Qux%', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', lowercase: false));
    }

    public function testItEscapesSqlSearchStringWithoutTrimming(): void
    {
        self::assertSame('% foo\\%bar\\_baz\\\\qux %', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', trim: false));
    }

    public function testItEscapesSqlSearchStringWithoutLowercasingAndWithoutTrimming(): void
    {
        self::assertSame('% Foo\\%Bar\\_Baz\\\\Qux %', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', lowercase: false, trim: false));
    }

    public function testItDoesNotEscapeStrictSearchString(): void
    {
        self::assertSame(' Foo%Bar_Baz\\Qux ', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', true));
    }

    public function testItEscapesSqlSearchStringList(): void
    {
        self::assertSame(
            [
                '%Foo\\_Bar%',
                '%Baz\\%Qux%',
            ],
            SearchHelper::sqlSearchString(['Foo_Bar', 'Baz%Qux'], lowercase: false)
        );
    }

    public function testItEscapesSqlSearchStringListWithoutTrimming(): void
    {
        self::assertSame(
            [
                '% Foo\\_Bar %',
                '% Baz\\%Qux %',
            ],
            SearchHelper::sqlSearchString([' Foo_Bar ', ' Baz%Qux '], lowercase: false, trim: false)
        );
    }

    public function testItDoesNotLowercaseWhenStrictModeIsEnabled(): void
    {
        self::assertSame(' Foo%Bar_Baz\\Qux ', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', strict: true, lowercase: false));
    }

    public function testItDoesNotTrimWhenStrictModeIsEnabled(): void
    {
        self::assertSame(' Foo%Bar_Baz\\Qux ', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', strict: true, trim: false));
    }

    public function testItIgnoresLowercaseAndTrimOptionsWhenStrictModeIsEnabled(): void
    {
        self::assertSame(
            ' Foo%Bar_Baz\\Qux ',
            SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux ', strict: true, lowercase: true, trim: true)
        );
    }

    public function testItProcessesLikeFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::like('name', false) => 'John',
        ]);

        self::assertSame(
            [
                'name' => [
                    [
                        'expFn' => FilterExprFn::Like,
                        'value' => '%john%',
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }

    public function testItProcessesEqualFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::equal('age', false) => 42,
        ]);

        self::assertSame(
            [
                'age' => [
                    [
                        'expFn' => FilterExprFn::Equal,
                        'value' => 42,
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }

    public function testItProcessesArrayAsInFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::equal('id', false) => [1, 2, 3],
        ]);

        self::assertSame(
            [
                'id' => [
                    [
                        'expFn' => FilterExprFn::In,
                        'value' => [1, 2, 3],
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }

    public function testItProcessesNotEqualArrayAsNotInFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::notEqual('id', false) => [1, 2, 3],
        ]);

        self::assertSame(
            [
                'id' => [
                    [
                        'expFn' => FilterExprFn::NotIn,
                        'value' => [1, 2, 3],
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }

    public function testItProcessesNullFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::null('deletedAt', false) => true,
        ]);

        self::assertSame(
            [
                'deletedAt' => [
                    [
                        'expFn' => FilterExprFn::IsNull,
                        'value' => SearchHelper::NULL_VALUE,
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }

    public function testItIgnoresEmptyConditionalFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::filter('name', false) => '',
        ]);

        self::assertSame([], $helper->getClauseFilters());
    }

    public function testItProcessesNonEmptyConditionalFilterAsEqualFilter(): void
    {
        $helper = new SearchHelper([
            SearchFilter::filter('name', false) => 'John',
        ]);

        self::assertSame(
            [
                'name' => [
                    [
                        'expFn' => FilterExprFn::Equal,
                        'value' => 'John',
                    ],
                ],
            ],
            $helper->getClauseFilters()
        );
    }
}