<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
 * Constructeur de clauses SQL pour la construction de requêtes Doctrine DBAL.
 *
 * Cette classe ne contient que les spécificités DBAL. L'algorithme commun de
 * construction des clauses WHERE est porté par AbstractDoctrineClauseBuilder.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @extends AbstractDoctrineClauseBuilder<DBALQueryBuilder>
 */
class DBALClauseBuilder extends AbstractDoctrineClauseBuilder
{
    public function __construct(
        private readonly DBALQueryBuilder $dbalQueryBuilder,
    ) {
    }

    protected function getWrappedQueryBuilder(): DBALQueryBuilder
    {
        return $this->dbalQueryBuilder;
    }

    protected function createAndComposite(): CompositeExpression
    {
        return $this->dbalQueryBuilder->expr()->and('1=1');
    }

    protected function createOrComposite(): CompositeExpression
    {
        return $this->dbalQueryBuilder->expr()->or('1=0');
    }

    protected function addToComposite(\Stringable $stringable, string|\Stringable $expression): CompositeExpression
    {
        /** @var CompositeExpression $stringable */
        return $stringable->with((string) $expression);
    }

    protected function andWhere(string|\Stringable $expression): void
    {
        $this->dbalQueryBuilder->andWhere(
            $expression instanceof CompositeExpression ? $expression : (string) $expression,
        );
    }

    protected function orWhere(string|\Stringable $expression): void
    {
        $this->dbalQueryBuilder->orWhere(
            $expression instanceof CompositeExpression ? $expression : (string) $expression,
        );
    }

    protected function setParameter(string $name, mixed $value): void
    {
        $this->dbalQueryBuilder->setParameter($name, $value, $this->resolveParameterType($value));
    }

    protected function buildExpression(
        string $field,
        string $parameter,
        FilterExprFn $filterExprFn,
        bool $escapedLike = false,
    ): string {
        $escapeChar = $escapedLike ? $this->quoteLikeEscapeCharacter() : null;

        return match ($filterExprFn) {
            FilterExprFn::IsNull,
            FilterExprFn::IsNotNull => $this->dbalQueryBuilder->expr()->{$filterExprFn->value}($field),
            FilterExprFn::Like,
            FilterExprFn::NotLike => $this->dbalQueryBuilder->expr()->{$filterExprFn->value}($field, $parameter, $escapeChar),
            default => $this->dbalQueryBuilder->expr()->{$filterExprFn->value}($field, $parameter),
        };
    }

    protected function initializeOrderBy(?string $paginatorSort): void
    {
        $sorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] === $sorts) {
            return;
        }

        $this->dbalQueryBuilder->resetOrderBy();

        foreach ($sorts as $sort) {
            $this->dbalQueryBuilder->addOrderBy($sort->field, $sort->direction);
        }
    }

    private function quoteLikeEscapeCharacter(): string
    {
        return "'".str_replace("'", "''", SearchHelper::LIKE_ESCAPE_CHARACTER)."'";
    }

    private function resolveParameterType(mixed $value): ArrayParameterType|ParameterType
    {
        if (is_array($value)) {
            return isset($value[0]) && is_int($value[0])
                ? ArrayParameterType::INTEGER
                : ArrayParameterType::STRING;
        }

        return match (true) {
            is_int($value) => ParameterType::INTEGER,
            is_bool($value) => ParameterType::BOOLEAN,
            default => ParameterType::STRING,
        };
    }
}
