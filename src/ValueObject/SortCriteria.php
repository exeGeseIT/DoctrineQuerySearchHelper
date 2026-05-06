<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper\ValueObject;

final readonly class SortCriteria
{
    public function __construct(
        public string $field,
        public string $direction,
    ) {
    }
}
