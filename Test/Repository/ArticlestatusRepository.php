<?php

namespace ExeGeseIT\Test\Repository;

use ExeGeseIT\Test\Entity\Articlestatus;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Articlestatus>
 *
 * @method Articlestatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method Articlestatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method Articlestatus[]    findAll()
 * @method Articlestatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class ArticlestatusRepository extends EntityRepository
{
}
