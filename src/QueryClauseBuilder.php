<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
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

    public static function getInstance(QueryBuilder|QueryBuilderDBAL $qb): ClauseBuilderInterface
    {
        return match (true) {
            $qb instanceof QueryBuilder => new DQLClauseBuilder($qb),
            default => new DBALClauseBuilder($qb),
        };
    }
}
