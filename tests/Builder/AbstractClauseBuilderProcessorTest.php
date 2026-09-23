<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Tests\Builder;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\Builder\AbstractClauseBuilderProcessor;
use ExeGeseIT\DoctrineQuerySearchHelper\ValueObject\SortCriteria;
use PHPUnit\Framework\TestCase;

final class AbstractClauseBuilderProcessorTest extends TestCase
{
    /**
     * @var AbstractClauseBuilderProcessor<QueryBuilder|QueryBuilderDBAL>
     */
    private AbstractClauseBuilderProcessor $clauseBuilderProcessor;

    protected function setUp(): void
    {
        $this->clauseBuilderProcessor = new class() extends AbstractClauseBuilderProcessor {
            /**
             * @param array<string, mixed>|null $search
             */
            public function getQueryBuilder(?array $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL
            {
                throw new \LogicException('Not needed for this test.');
            }

            /**
             * @return list<SortCriteria>
             */
            public function publicNormalizePaginatorSort(string $paginatorSort): array
            {
                return $this->normalizePaginatorSort($paginatorSort);
            }
        };
    }

    public function testItReturnsEmptyArrayWhenSortStringIsEmpty(): void
    {
        self::assertSame([], $this->callNormalizePaginatorSort(''));
        self::assertSame([], $this->callNormalizePaginatorSort('   '));
        self::assertSame([], $this->callNormalizePaginatorSort(',,,   ,'));
    }

    public function testItNormalizesSingleFieldWithDefaultDirection(): void
    {
        $result = $this->callNormalizePaginatorSort('name');

        self::assertEquals([
            new SortCriteria(field: 'name', direction: 'ASC'),
        ], $result);
    }

    public function testItNormalizesSingleFieldWithExplicitAscDirection(): void
    {
        $result = $this->callNormalizePaginatorSort('createdAt ASC');

        self::assertEquals([
            new SortCriteria(field: 'createdAt', direction: 'ASC'),
        ], $result);
    }

    public function testItNormalizesSingleFieldWithDescDirection(): void
    {
        $result = $this->callNormalizePaginatorSort('updatedAt DESC');

        self::assertEquals([
            new SortCriteria(field: 'updatedAt', direction: 'DESC'),
        ], $result);
    }

    public function testItHandlesCaseInsensitiveDirection(): void
    {
        $resultAsc = $this->callNormalizePaginatorSort('name asc');
        $resultDesc = $this->callNormalizePaginatorSort('name desc');

        self::assertEquals([
            new SortCriteria(field: 'name', direction: 'ASC'),
        ], $resultAsc);

        self::assertEquals([
            new SortCriteria(field: 'name', direction: 'DESC'),
        ], $resultDesc);
    }

    public function testItDefaultsToAscWhenDirectionIsInvalid(): void
    {
        $result = $this->callNormalizePaginatorSort('name INVALID_DIRECTION');

        self::assertEquals([
            new SortCriteria(field: 'name', direction: 'ASC'),
        ], $result);
    }

    public function testItNormalizesMultipleFieldsWithVariousFormats(): void
    {
        $result = $this->callNormalizePaginatorSort('  dwh.organizationkey DESC ,  user.name asc, age   ');

        self::assertEquals([
            new SortCriteria(field: 'dwh.organizationkey', direction: 'DESC'),
            new SortCriteria(field: 'user.name', direction: 'ASC'),
            new SortCriteria(field: 'age', direction: 'ASC'),
        ], $result);
    }

    public function testItNormalizesCombinedFieldsWithPlusSeparator(): void
    {
        $result = $this->callNormalizePaginatorSort('firstName+lastName DESC, age+createdAt ASC, score');

        self::assertEquals([
            new SortCriteria(field: 'firstName', direction: 'DESC'),
            new SortCriteria(field: 'lastName', direction: 'DESC'),
            new SortCriteria(field: 'age', direction: 'ASC'),
            new SortCriteria(field: 'createdAt', direction: 'ASC'),
            new SortCriteria(field: 'score', direction: 'ASC'),
        ], $result);
    }

    public function testItHandlesCombinedFieldsWithInvalidOrEmptyParts(): void
    {
        $result = $this->callNormalizePaginatorSort('validField+123invalid++otherField DESC');

        self::assertEquals([
            new SortCriteria(field: 'validField', direction: 'DESC'),
            new SortCriteria(field: 'otherField', direction: 'DESC'),
        ], $result);
    }

    public function testItAllowsValidFieldCharacters(): void
    {
        $result = $this->callNormalizePaginatorSort('_privateField ASC, entity.field_name_123 DESC');

        self::assertEquals([
            new SortCriteria(field: '_privateField', direction: 'ASC'),
            new SortCriteria(field: 'entity.field_name_123', direction: 'DESC'),
        ], $result);
    }

    public function testItIgnoresInvalidFieldNames(): void
    {
        $result = $this->callNormalizePaginatorSort('123invalid ASC, user-name DESC, validField ASC, user;drop table ASC');

        self::assertEquals([
            new SortCriteria(field: 'validField', direction: 'ASC'),
        ], $result);
    }

    /**
     * @return list<SortCriteria>
     */
    private function callNormalizePaginatorSort(string $paginatorSort): array
    {
        /** @var callable(string): list<SortCriteria> $callable */
        $callable = [$this->clauseBuilderProcessor, 'publicNormalizePaginatorSort'];

        return $callable($paginatorSort);
    }
}
