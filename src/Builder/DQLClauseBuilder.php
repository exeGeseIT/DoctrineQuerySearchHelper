<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\Builder;

use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;

/**
 * Constructeur de clauses DQL pour la construction de requêtes Doctrine ORM.
 *
 * Cette classe ne contient que les spécificités ORM. L'algorithme commun de
 * construction des clauses WHERE est porté par AbstractDoctrineClauseBuilder.
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 *
 * @extends AbstractDoctrineClauseBuilder<QueryBuilder>
 */
class DQLClauseBuilder extends AbstractDoctrineClauseBuilder
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
    ) {
    }

    protected function getWrappedQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    protected function createAndComposite(): Composite
    {
        return $this->queryBuilder->expr()->andX();
    }

    protected function createOrComposite(): Composite
    {
        return $this->queryBuilder->expr()->orX();
    }

    protected function addToComposite(\Stringable $stringable, string|\Stringable $expression): Composite
    {
        /** @var Composite $stringable */
        $stringable->add($expression);

        return $stringable;
    }

    protected function andWhere(string|\Stringable $expression): void
    {
        $this->queryBuilder->andWhere($expression);
    }

    protected function orWhere(string|\Stringable $expression): void
    {
        $this->queryBuilder->orWhere($expression);
    }

    protected function setParameter(string $name, mixed $value): void
    {
        $this->queryBuilder->setParameter($name, $value);
    }

    protected function buildExpression(
        string $field,
        string $parameter,
        FilterExprFn $filterExprFn,
        bool $escapedLike = false,
    ): string {
        $expression = match ($filterExprFn) {
            FilterExprFn::IsNull,
            FilterExprFn::IsNotNull => $this->queryBuilder->expr()->{$filterExprFn->value}($field),
            default => $this->queryBuilder->expr()->{$filterExprFn->value}($field, $parameter),
        };

        if ($escapedLike && in_array($filterExprFn, [FilterExprFn::Like, FilterExprFn::NotLike], true)) {
            return sprintf("%s ESCAPE '%s'", $expression, SearchHelper::LIKE_ESCAPE_CHARACTER);
        }

        return (string) $expression;
    }

    protected function initializeOrderBy(?string $paginatorSort): void
    {
        $sorts = $this->normalizePaginatorSort($paginatorSort ?? '');

        if ([] === $sorts) {
            return;
        }

        $initialOrder = $this->queryBuilder->getDQLPart('orderBy');

        if (!is_iterable($initialOrder)) {
            $initialOrder = [$initialOrder];
        }

        $this->queryBuilder->resetDQLPart('orderBy');

        foreach ($sorts as $sort) {
            $this->queryBuilder->addOrderBy($sort->field, $sort->direction);
        }

        /** @var OrderBy $sort */
        foreach ($initialOrder as $sort) {
            $this->queryBuilder->addOrderBy($sort);
        }
    }
}
