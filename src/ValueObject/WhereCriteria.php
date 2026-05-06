<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\ValueObject;

use ExeGeseIT\DoctrineQuerySearchHelper\FilterExprFn;

/**
 * @phpstan-type SearchValue float|int|string|list<float|int|string>
 */
final readonly class WhereCriteria
{
    /**
     * @param SearchValue $value
     */
    public function __construct(
        public FilterExprFn $filterExprFn,
        public mixed $value,
        public bool $escapedLike = false,
    ) {
    }

    public function hasArrayValue(): bool
    {
        return is_array($this->value);
    }

    public function isInExpression(): bool
    {
        return in_array($this->filterExprFn, [FilterExprFn::In, FilterExprFn::NotIn], true);
    }

    public function shouldExpandArrayAsOrConditions(): bool
    {
        return $this->hasArrayValue() && !$this->isInExpression();
    }
}
