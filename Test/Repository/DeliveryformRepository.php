<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Repository;

use Doctrine\ORM\EntityRepository;
use ExeGeseIT\Test\Entity\Deliveryform;

/**
 * @extends EntityRepository<Deliveryform>
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class DeliveryformRepository extends EntityRepository
{
}
