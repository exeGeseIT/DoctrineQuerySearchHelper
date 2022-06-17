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
     * @param iterable $searchFields [searchKey => field]
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
     * If these searchKey appear in the $search array without any filter a LIKE filter is implicitly applied.
     * In other words, for such a searchKey, these two definitions are equivalent:
     *    SearchFilter::filter('default_like_searchkey') => 'foo',
     *    SearchFilter::like('default_like_searchkey') => 'foo',
     * 
     * @param iterable $likeFields [searchKey => field]
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
        
        if ( empty($this->defaultLike) ) {
            return $search;
        }
        
        $_search = [];
        foreach ($search as $searchfilter => $value) {
            
            $m = SearchFilter::decodeSearchfilter($searchfilter);
            if ( empty($m['filter']) && in_array($m['key'], $this->defaultLike) ) {
                $_search[ SearchFilter::like($m['key']) ] = ctype_print($value) ? trim($value) : $value;
            } else {
                $_search[ $searchfilter ] = $value;
            }
        }
        
        return $_search;
    }
            
}
