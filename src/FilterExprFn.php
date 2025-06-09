<?php

declare(strict_types=1);

namespace ExeGeseIT\DoctrineQuerySearchHelper;

enum FilterExprFn: string
{
    case Equal = 'eq';
    case NotEqual = 'neq';
    case In = 'in';
    case NotIn = 'notIn';
    case Lower = 'lt';
    case LowerOrEqual = 'lte';
    case Greater = 'gt';
    case GreaterOrEqual = 'gte';
    case Like = 'like';
    case NotLike = 'notLike';
    case IsNull = 'isNull';
    case IsNotNull = 'isNotNull';

    public function value(): string
    {
        return $this->value;
    }
}
