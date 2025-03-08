<?php

namespace ExeGeseIT\Test\Repository;

use ExeGeseIT\Test\Entity\Deliveryform;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Deliveryform>
 *
 * @method Deliveryform|null find($id, $lockMode = null, $lockVersion = null)
 * @method Deliveryform|null findOneBy(array $criteria, array $orderBy = null)
 * @method Deliveryform[]    findAll()
 * @method Deliveryform[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class DeliveryformRepository extends EntityRepository
{
}
