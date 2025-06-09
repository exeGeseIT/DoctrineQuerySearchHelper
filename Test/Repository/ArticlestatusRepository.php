<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Repository;

use Doctrine\ORM\EntityRepository;
use ExeGeseIT\Test\Entity\Articlestatus;

/**
 * @extends EntityRepository<Articlestatus>
 *
 * @phpstan-import-type TSearch from SearchHelper
 */
class ArticlestatusRepository extends EntityRepository
{
}
