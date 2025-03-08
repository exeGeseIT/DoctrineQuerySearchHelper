<?php

namespace ExeGeseIT\Test\Repository;

use ExeGeseIT\Test\Entity\Deliveryformstatus;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Deliveryformstatus>
 *
 * @method Deliveryformstatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method Deliveryformstatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method Deliveryformstatus[]    findAll()
 * @method Deliveryformstatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class DeliveryformstatusRepository extends EntityRepository
{
}
