<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Tests\Builder;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use PHPUnit\Framework\TestCase;

final class DBALClauseBuilderTest extends TestCase
{
    public function testItGeneratesEqualWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'organizationkey' => 'dwh.organizationkey',
            ])
            ->getQueryBuilder([
                SearchFilter::equal('organizationkey', false) => 'CFA_MEDERIC',
            ], null);

        self::assertSame(
            'dwh.organizationkey = :organizationkey_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('CFA_MEDERIC', $queryBuilder->getParameter('organizationkey_i0'));
    }

    public function testItGeneratesNotEqualWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'type' => 'dwh.type',
            ])
            ->getQueryBuilder([
                SearchFilter::notEqual('type', false) => 'BUDGET',
            ], null);

        self::assertSame(
            'dwh.type <> :type_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('BUDGET', $queryBuilder->getParameter('type_i0'));
    }

    public function testItGeneratesLikeWhereClauseWithEscape(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::like('extra3', false) => 'Projet_100%',
            ], null);

        self::assertSame(
            "dwh.extra3 LIKE :extra3_i0 ESCAPE '\\'",
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('%projet\\_100\\%%', $queryBuilder->getParameter('extra3_i0'));
    }

    public function testItGeneratesLikeStrictWhereClauseWithoutEscape(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::likeStrict('extra3', false) => 'Projet%',
            ], null);

        self::assertSame(
            'dwh.extra3 LIKE :extra3_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('Projet%', $queryBuilder->getParameter('extra3_i0'));
    }

    public function testItGeneratesNotLikeWhereClauseWithEscape(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::notLike('extra3', false) => 'Projet_100%',
            ], null);

        self::assertSame(
            "dwh.extra3 NOT LIKE :extra3_i0 ESCAPE '\\'",
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('%projet\\_100\\%%', $queryBuilder->getParameter('extra3_i0'));
    }

    public function testItGeneratesInWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'archivestatus' => 'dwh.archivestatus',
            ])
            ->getQueryBuilder([
                SearchFilter::equal('archivestatus', false) => [1, 2, 3],
            ], null);

        self::assertSame(
            'dwh.archivestatus IN (:archivestatus_i0)',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame([1, 2, 3], $queryBuilder->getParameter('archivestatus_i0'));
    }

    public function testItGeneratesNotInWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'archivestatus' => 'dwh.archivestatus',
            ])
            ->getQueryBuilder([
                SearchFilter::notEqual('archivestatus', false) => [1, 2, 3],
            ], null);

        self::assertSame(
            'dwh.archivestatus NOT IN (:archivestatus_i0)',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame([1, 2, 3], $queryBuilder->getParameter('archivestatus_i0'));
    }

    public function testItGeneratesIsNullWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::null('extra3', false) => true,
            ], null);

        self::assertSame(
            'dwh.extra3 IS NULL',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertFalse(array_key_exists('extra3_i0', $queryBuilder->getParameters()));
    }

    public function testItGeneratesIsNotNullWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::notNull('extra3', false) => true,
            ], null);

        self::assertSame(
            'dwh.extra3 IS NOT NULL',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertFalse(array_key_exists('extra3_i0', $queryBuilder->getParameters()));
    }

    public function testItGeneratesGreaterOrEqualWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'docdate' => 'dwh.docdate',
            ])
            ->getQueryBuilder([
                SearchFilter::greaterOrEqual('docdate', false) => '2025-01-01',
            ], null);

        self::assertSame(
            'dwh.docdate >= :docdate_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('2025-01-01', $queryBuilder->getParameter('docdate_i0'));
    }

    public function testItGeneratesLowerOrEqualWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'docdate' => 'dwh.docdate',
            ])
            ->getQueryBuilder([
                SearchFilter::lowerOrEqual('docdate', false) => '2025-12-31',
            ], null);

        self::assertSame(
            'dwh.docdate <= :docdate_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('2025-12-31', $queryBuilder->getParameter('docdate_i0'));
    }

    public function testItGeneratesMultipleSimpleWhereClauses(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'organizationkey' => 'dwh.organizationkey',
                'type' => 'dwh.type',
            ])
            ->getQueryBuilder([
                SearchFilter::equal('organizationkey', false) => 'CFA_MEDERIC',
                SearchFilter::equal('type', false) => 'BUDGET',
            ], null);

        self::assertSame(
            'dwh.organizationkey = :organizationkey_i0 AND dwh.type = :type_i0',
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('CFA_MEDERIC', $queryBuilder->getParameter('organizationkey_i0'));
        self::assertSame('BUDGET', $queryBuilder->getParameter('type_i0'));
    }

    public function testItIgnoresUnknownSearchField(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'organizationkey' => 'dwh.organizationkey',
            ])
            ->getQueryBuilder([
                SearchFilter::equal('unknown', false) => 'value',
            ], null);

        self::assertSame('', $this->getNormalizedWherePart($queryBuilder));
        self::assertSame([], $queryBuilder->getParameters());
    }

    public function testItGeneratesCompositeAndOrWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'organizationkey' => 'dwh.organizationkey',
                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
            ])
            ->getQueryBuilder([
                SearchFilter::equal('organizationkey', false) => 'CFA_MEDERIC',
                SearchFilter::andOr() => [
                    SearchFilter::equal('extra1', false) => 'foo',
                    SearchFilter::equal('extra2', false) => 'bar',
                ],
            ], null);

        $where = $this->getNormalizedWherePart($queryBuilder);

        self::assertStringContainsString('dwh.organizationkey = :organizationkey_i0', $where);
        self::assertMatchesRegularExpression('/AND\s*\(.*dwh\.extra1 = :[^ ]+.*OR.*dwh\.extra2 = :[^ )]+.*\)/', $where);

        self::assertSame('CFA_MEDERIC', $queryBuilder->getParameter('organizationkey_i0'));
        self::assertContains('foo', $this->getParameterValues($queryBuilder));
        self::assertContains('bar', $this->getParameterValues($queryBuilder));
    }

    public function testItGeneratesCompositeAndWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
            ])
            ->getQueryBuilder([
                SearchFilter::and() => [
                    SearchFilter::equal('extra1', false) => 'foo',
                    SearchFilter::equal('extra2', false) => 'bar',
                ],
            ], null);

        $where = trim($this->getNormalizedWherePart($queryBuilder), '()');

        self::assertMatchesRegularExpression(
            '/^1=1 AND dwh\.extra1 = :[^ ]+ AND dwh\.extra2 = :[^ ]+$/',
            $where
        );

        self::assertContains('foo', $this->getParameterValues($queryBuilder));
        self::assertContains('bar', $this->getParameterValues($queryBuilder));
    }

    public function testItGeneratesCompositeOrWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
            ])
            ->getQueryBuilder([
                SearchFilter::or() => [
                    SearchFilter::equal('extra1', false) => 'foo',
                    SearchFilter::equal('extra2', false) => 'bar',
                ],
            ], null);

        $where = trim($this->getNormalizedWherePart($queryBuilder), '()');

        self::assertMatchesRegularExpression(
            '/^1=1 AND dwh\.extra1 = :[^ ]+ AND dwh\.extra2 = :[^ ]+$/',
            $where
        );

        self::assertContains('foo', $this->getParameterValues($queryBuilder));
        self::assertContains('bar', $this->getParameterValues($queryBuilder));
    }

    public function testItGeneratesCompositeLikeWhereClauseWithEscape(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
            ])
            ->getQueryBuilder([
                SearchFilter::andOr() => [
                    SearchFilter::like('extra1', false) => 'Foo_',
                    SearchFilter::like('extra2', false) => 'Bar%',
                ],
            ], null);

        $where = $this->getNormalizedWherePart($queryBuilder);

        self::assertStringContainsString('dwh.extra1 LIKE :', $where);
        self::assertStringContainsString('dwh.extra2 LIKE :', $where);
        self::assertSame(2, substr_count($where, "ESCAPE '\\'"));

        self::assertContains('%foo\\_%', $this->getParameterValues($queryBuilder));
        self::assertContains('%bar\\%%', $this->getParameterValues($queryBuilder));
    }

    public function testItGeneratesNestedCompositeWhereClause(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([
                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::andOr() => [
                    SearchFilter::equal('extra1', false) => 'foo',
                    SearchFilter::and() => [
                        SearchFilter::equal('extra2', false) => 'bar',
                        SearchFilter::equal('extra3', false) => 'baz',
                    ],
                ],
            ], null);

        $where = $this->getNormalizedWherePart($queryBuilder);

        self::assertStringContainsString('dwh.extra1 = :', $where);
        self::assertStringContainsString('dwh.extra2 = :', $where);
        self::assertStringContainsString('dwh.extra3 = :', $where);
        self::assertContains('foo', $this->getParameterValues($queryBuilder));
        self::assertContains('bar', $this->getParameterValues($queryBuilder));
        self::assertContains('baz', $this->getParameterValues($queryBuilder));
    }

    public function testItAppliesDefaultLikeFields(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setDefaultLikeFields([
                'extra3' => 'dwh.extra3',
            ])
            ->getQueryBuilder([
                SearchFilter::filter('extra3', false) => 'Projet_100%',
            ], null);

        self::assertSame(
            "dwh.extra3 LIKE :extra3_i0 ESCAPE '\\'",
            $this->getNormalizedWherePart($queryBuilder)
        );

        self::assertSame('%projet\\_100\\%%', $queryBuilder->getParameter('extra3_i0'));
    }

    public function testItReplacesInitialOrderByWithPaginatorSort(): void
    {
        $queryBuilder = $this->createBaseQueryBuilder();
        $queryBuilder->orderBy('dwh.modifieddate', 'DESC');

        QueryClauseBuilder::getInstance($queryBuilder)
            ->setSearchFields([])
            ->getQueryBuilder([], 'dwh.organizationkey ASC');

        self::assertSame(
            'SELECT dwh.* FROM datawarehouse dwh ORDER BY dwh.organizationkey ASC',
            $this->normalizeSql($queryBuilder->getSQL())
        );
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return $connection
            ->createQueryBuilder()
            ->select('dwh.*')
            ->from('datawarehouse', 'dwh');
    }

    private function getNormalizedWherePart(QueryBuilder $queryBuilder): string
    {
        $sql = $this->normalizeSql($queryBuilder->getSQL());

        if (1 !== preg_match('/\sWHERE\s(.+?)(?:\sORDER\sBY\s.+)?$/i', $sql, $matches)) {
            return '';
        }

        return $matches[1];
    }

    private function normalizeSql(string $sql): string
    {
        $normalizedSql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        return preg_replace(
            '/\(([^()]+(?:=|<>|<|>|<=|>=|LIKE|NOT LIKE|IN|NOT IN|IS NULL|IS NOT NULL)[^()]*)\)/i',
            '$1',
            $normalizedSql
        ) ?? $normalizedSql;
    }

    /**
     * @return list<mixed>
     */
    private function getParameterValues(QueryBuilder $queryBuilder): array
    {
        return array_values($queryBuilder->getParameters());
    }
}
