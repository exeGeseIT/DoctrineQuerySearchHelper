<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Tests;

use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\WhereCriteria;
use PHPUnit\Framework\TestCase;

final class SearchHelperTest extends TestCase
{
    public function testItEscapesSqlSearchString(): void
    {
        self::assertSame('%foo\\%bar\\_baz\\\\qux%', SearchHelper::sqlSearchString(' Foo%Bar_Baz\\Qux '));
    }

    public function testItEscapesLikeEscapeCharacter(): void
    {
        self::assertSame('%foo\\\\bar%', SearchHelper::sqlSearchString('foo\\bar'));
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
        $searchHelper = new SearchHelper([
            SearchFilter::like('name', false) => 'John',
        ]);

        self::assertEquals(
            [
                'name' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::Like,
                        value: '%john%',
                        escapedLike: true,
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesNotLikeFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::notLike('name', false) => 'John',
        ]);

        self::assertEquals(
            [
                'name' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::NotLike,
                        value: '%john%',
                        escapedLike: true,
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesLikeStrictFilterWithoutEscapedLikeFlag(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::likeStrict('name', false) => 'John%',
        ]);

        self::assertEquals(
            [
                'name' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::Like,
                        value: 'John%',
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesNotLikeStrictFilterWithoutEscapedLikeFlag(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::notLikeStrict('name', false) => 'John%',
        ]);

        self::assertEquals(
            [
                'name' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::NotLike,
                        value: 'John%',
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesEqualFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::equal('age', false) => 42,
        ]);

        self::assertEquals(
            [
                'age' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::Equal,
                        value: 42,
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesArrayAsInFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::equal('id', false) => [1, 2, 3],
        ]);

        self::assertEquals(
            [
                'id' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::In,
                        value: [1, 2, 3],
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesNotEqualArrayAsNotInFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::notEqual('id', false) => [1, 2, 3],
        ]);

        self::assertEquals(
            [
                'id' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::NotIn,
                        value: [1, 2, 3],
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItProcessesNullFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::null('deletedAt', false) => true,
        ]);

        self::assertEquals(
            [
                'deletedAt' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::IsNull,
                        value: SearchHelper::NULL_VALUE,
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }

    public function testItIgnoresEmptyConditionalFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::filter('name', false) => '',
        ]);

        self::assertSame([], $searchHelper->getClauseFilters());
    }

    public function testItProcessesNonEmptyConditionalFilterAsEqualFilter(): void
    {
        $searchHelper = new SearchHelper([
            SearchFilter::filter('name', false) => 'John',
        ]);

        self::assertEquals(
            [
                'name' => [
                    new WhereCriteria(
                        filterExprFn: FilterExprFn::Equal,
                        value: 'John',
                    ),
                ],
            ],
            $searchHelper->getClauseFilters()
        );
    }
}
