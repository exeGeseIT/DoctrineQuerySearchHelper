<?php

namespace ExeGeseIT\DoctrineQuerySearchHelper;

use Doctrine\DBAL\Query\QueryBuilder as QueryBuilderDBAL;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Description of QuerySearchFilterFactory
 *
 * @author Jean-Claude GLOMBARD <jc.glombard@gmail.com>
 */
class QueryClauseBuilder
{
    /**
     * @var \Doctrine\ORM\QueryBuilder | \Doctrine\DBAL\Query\QueryBuilder
     */
    private $qb;
    private $searchFields = [];
    private $defaultLike = [];
    
    private function __construct()
    {}
    
    /**
     * 
     * @param \Doctrine\ORM\QueryBuilder | \Doctrine\DBAL\Query\QueryBuilder $qb
     * @return self
     * @throws InvalidArgumentException
     */
    public static function getInstance($qb): self
    {
        if ( !($qb instanceof QueryBuilder || $qb instanceof QueryBuilderDBAL) ) {
            throw new InvalidArgumentException( sprintf('$qb should be instance of %s or %s class', QueryBuilder::class, QueryBuilderDBAL::class) );
        }
        
        $instance = new self();
        $instance->qb = $qb;
        return $instance;
    }
    
    
    /**
     * 
     * @param iterable $searchFields    [searchKey => field]
     * @return self
     */
    public function setSearchFields(iterable $searchFields): self
    {
        foreach ($searchFields as $searchKey => $field) {
            $this->searchFields[ $searchKey ] = $field;
        }
        return $this;
    }
    
    /**
     * 
     * @param iterable $likeFields    [searchKey => field]
     * @return self
     */
    public function setDefaultLikeFields(iterable $likeFields): self
    {
        foreach ($likeFields as $searchKey => $field) {
            $this->searchFields[ $searchKey ] = $field;
            $this->defaultLike[] = $searchKey;
        }
        return $this;
    }
    
    public function getQueryBuilder(?iterable $search, ?string $paginatorSort): QueryBuilder|QueryBuilderDBAL
    {
        $searchFilters = $this->getSearchFilters($search);
        
        SearchHelper::initializeQbPaginatorOrderby($this->qb, $paginatorSort);
        SearchHelper::setQbSearchClause($this->qb, $searchFilters, $this->searchFields);
        
        return $this->qb;
    }
    
    
    /**
     * 
     * @param iterable|null $search
     * @return iterable
     */
    private function getSearchFilters(?iterable $search): iterable
    {
        if ( empty($search) ) {
            return [];
        }
        
        foreach ($this->defaultLike as $key) {
            if ( array_key_exists($key, $search) ) {
                $value = $search[ $key ];
                $search[ '%' . $key ] = ctype_print($value) ? trim($value) : $value;
                unset($search[$key]);
            }
        }
        
        return $search;
    }
            
}
