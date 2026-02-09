<?php

declare(strict_types=1);

use ExeGeseIT\DoctrineQuerySearchHelper\QueryClauseBuilder;
use ExeGeseIT\Test\Entity\Datawarehouse\Datawarehouseaccounting;

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use Symfony\Component\VarExporter\VarExporter;

// Create a simple "default" Doctrine ORM configuration for Attributes
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . 'test2.php/'],
    isDevMode: true,
);

// configuring the database connection
$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/db.sqlite',
], $config);

// obtaining the entity manager
$entityManager = new EntityManager($connection, $config);

$searchData = [
    SearchFilter::equal('keyorganization') => 'CFA_MEDERIC',
    SearchFilter::equal('colid') => 'coll_1',
    SearchFilter::equal('archivestatus') => 1,
    SearchFilter::equal('type') => 'BUDGET',
    SearchFilter::equal('extra1') => 'Frais Generaux',
    SearchFilter::equal('extra2') => 'Frais Generaux',
    SearchFilter::andOr() => [
        SearchFilter::equal('glaccount') => '64510000 - COTISATIONS URSSAF',
        SearchFilter::equal('glaccount') => '',
        SearchFilter::null('glaccount') => true,
    ],
    SearchFilter::greaterOrEqual('docdate') => '2025-01-01 00:00:00',
    SearchFilter::lowerOrEqual('docdate') => '2025-12-31 00:00:00',
];

echo VarExporter::export($searchData);
echo "\n";
echo SearchHelper::dumpParsedSearchParameters($searchData, pretty: true);
echo "\n";

/** @var QueryBuilder $queryBuilder */
$queryBuilder = $entityManager->getRepository(Datawarehouseaccounting::class)->fetchDatawarehouseaccountingQb($searchData);

echo sprintf("\n%s\n", QueryClauseBuilder::dumpQueryClause($queryBuilder));
