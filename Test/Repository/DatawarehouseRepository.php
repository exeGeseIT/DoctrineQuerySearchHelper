<?php

declare(strict_types=1);

namespace ExeGeseIT\Test\Repository;

use App\Entity\Datawarehouse\Datawarehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;

/**
 * @extends EntityRepository<Datawarehouse>
 */
class DatawarehouseRepository extends EntityRepository
{
    /**
     * @param array<string, mixed> $search
     */
    public function fetchDatawarehouseQb(array $search = [], string $paginatorSort = ''): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('dwh');

        $queryBuilder->addOrderBy('dwh.modifieddate', 'DESC');

        $queryClauseBuilder = QueryClauseBuilder::getInstance($queryBuilder);
        $queryClauseBuilder
            ->setSearchFields([
                'keyorganization' => 'dwh.organizationkey',
                'organizationkey' => 'dwh.organizationkey',
                'collid' => 'dwh.collid',
                'coll_id' => 'dwh.collid',

                'action' => 'dwh.action',

                'type' => 'dwh.type',
                'status' => 'dwh.docstatus',
                'docstatus' => 'dwh.docstatus',
                'workflowstatus' => 'dwh.docstatus',
                'creditdebit' => 'dwh.creditdebit',
                'thirdparty' => 'dwh.thirdparty',
                'owner' => 'dwh.owner',

                'bu' => 'dwh.businessunit',
                'businessunit' => 'dwh.businessunit',

                'reference' => 'dwh.reference',
                'externalref' => 'dwh.externalref',

                'poref' => 'dwh.poref',
                'orderref' => 'dwh.poref',
                'internalref' => 'dwh.poref',

                'extra1' => 'dwh.extra1',
                'extra2' => 'dwh.extra2',
                'extra3' => 'dwh.extra3',
                'extra4' => 'dwh.extra4',
                'extra5' => 'dwh.extra5',
                'extra6' => 'dwh.extra6',
                'extra7' => 'dwh.extra7',
                'extra8' => 'dwh.extra8',
                'extra9' => 'dwh.extra9',
                'extra10' => 'dwh.extra10',

                'currency' => 'dwh.currency',
                'supervisor' => 'dwh.supervisor',

                'docdate' => 'dwh.docdate',
                'docdate_year' => 'YEAR(dwh.docdate)',
                'docdate_yearmonth' => 'YEARMONTH(dwh.docdate)',
                'docdate_yearweek' => 'YEARWEEK(dwh.docdate)',
                'docdate_month' => 'MONTH(dwh.docdate)',
                'docdate_week' => 'WEEK(dwh.docdate)',

                'createdate' => 'dwh.createdate',

                'archivestatus' => 'dwh.archivestatus',
                'zstatus' => 'dwh.archivestatus',
            ])
        ;

        return $queryClauseBuilder->getQueryBuilder($search, $paginatorSort);
    }
}
