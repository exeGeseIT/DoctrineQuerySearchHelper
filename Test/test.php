<?php

declare(strict_types=1);

use Doctrine\ORM\Querybuilder;

require_once __DIR__ . '/test.autoloader.php';

// bootstrap.php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchFilter;
use ExeGeseIT\DoctrineQuerySearchHelper\SearchHelper;
use ExeGeseIT\Test\Entity\Article;
use ExeGeseIT\Test\Entity\Articlestatus;
use ExeGeseIT\Test\Entity\Deliveryformstatus;
use Symfony\Component\VarExporter\VarExporter;

// require_once __DIR__ . '/../vendor/autoload.php';

// Create a simple "default" Doctrine ORM configuration for Attributes
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/'],
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
    SearchFilter::equal('articleStatus') => [Articlestatus::LOADED, Articlestatus::REFUSED, Articlestatus::REMOVAL_MISSING, Articlestatus::RETURNED],
    SearchFilter::notEqual('articleStatus') => Articlestatus::RETURNED,
    SearchFilter::andOr() => [
        SearchFilter::equal('isremoval') => true,
        SearchFilter::equal('deliveryformStatus') => [Deliveryformstatus::ABSENT, Deliveryformstatus::DELIVERED],
        SearchFilter::or() => [
            SearchFilter::equal('deliveryformStatus') => Deliveryformstatus::DOCKED,
            SearchFilter::equal('isremoval') => false,
        ],
    ],
    SearchFilter::or() => [
        SearchFilter::equal('deliveryformStatus') => Deliveryformstatus::DOCKED,
        SearchFilter::equal('isremoval') => false,
    ],
    SearchFilter::filter('idarticle') => null,

];

echo VarExporter::export($searchData);
echo "\n";
echo SearchHelper::dumpParsedSearchParameters($searchData, pretty: true);
echo "\n";

/** @var Querybuilder $queryBuilder */
$queryBuilder = $entityManager->getRepository(Article::class)->fetchArticleQb($searchData);
echo "\n";
echo (new SqlFormatter(new NullHighlighter()))->format($queryBuilder->getQuery()->getSQL());
echo "\n";

// expects array<string, array<array<string, array<array{expFn: string, value: array<int, int|string>|bool|float|int|string}|int|string>|bool|float|int|string>>>
// given   array<string, array<int|string, array<array<int|string, array<array{expFn: string, value: array<int, int|string>|bool|float|int|string}|int|string>|bool|float|int|string>|float|int|string>>>
