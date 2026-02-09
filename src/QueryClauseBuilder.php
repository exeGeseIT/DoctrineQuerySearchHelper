<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use ExeGeseIT\DoctrineQuerySearchHelper\Builder\ClauseBuilderInterface;
use ExeGeseIT\DoctrineQuerySearchHelper\Builder\DBALClauseBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\Builder\DQLClauseBuilder;

/**
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
final class QueryClauseBuilder
{
    private function __construct()
    {
    }

    /**
     * @template T of QueryBuilder|QueryBuilderDBAL
     *
     * @param T $qb
     *
     * @return (T is QueryBuilder  ? DQLClauseBuilder : DBALClauseBuilder)
     */
    public static function getInstance(QueryBuilder|QueryBuilderDBAL $qb): ClauseBuilderInterface
    {
        return match (true) {
            $qb instanceof QueryBuilder => new DQLClauseBuilder($qb),
            default => new DBALClauseBuilder($qb),
        };
    }

    public static function dumpQueryClause(QueryBuilder|QueryBuilderDBAL $qb, bool $highlighted = true, bool $compressed = false, bool $returnFullQuery = false): string
    {
        $sql = match (true) {
            $qb instanceof QueryBuilder => $qb->getQuery()->getSQL(),
            default => $qb->getSQL(),
        };

        $sql = is_array($sql) ? $sql[0] : $sql;

        $clause = $returnFullQuery ? $sql : match (preg_match('/FROM(.+)$/s', $sql, $matches)) {
            false => $sql,
            default => '... FROM ' . $matches[1],
        };

        $sqlFormatter = new SqlFormatter($highlighted ? null : new NullHighlighter());

        return match ($compressed) {
            true => $sqlFormatter->compress($clause),
            default => $sqlFormatter->format($clause),
        };
    }
}
