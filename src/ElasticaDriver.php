<?php

namespace FSi\Component\DataSource\Driver\Elastica;

use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query;
use Elastica\SearchableInterface;
use FSi\Component\DataSource\Driver\DriverAbstract;

class ElasticaDriver extends DriverAbstract
{
    /**
     * @var \Elastica\Query\BoolQuery
     */
    private $filters;

    /**
     * @var \Elastica\Query\BoolQuery
     */
    private $subQueries;

    /**
     * @var \Elastica\Query
     */
    private $query;

    /**
     * @var \Elastica\SearchableInterface
     */
    private $searchable;

    /**
     * @var \Elastica\Query\AbstractQuery
     */
    private $userSubQuery;

    /**
     * @var \Elastica\Query\AbstractQuery
     */
    private $userFilter;

    /**
     * @var \Elastica\Query
     */
    private $masterQuery;

    /**
     * @param $extensions array with extensions
     * @param SearchableInterface $searchable
     * @param AbstractQuery $userSubQuery
     * @param AbstractQuery $userFilter
     * @param Query $masterQuery
     * @throws \FSi\Component\DataSource\Exception\DataSourceException
     */
    public function __construct(
        $extensions,
        SearchableInterface $searchable,
        AbstractQuery $userSubQuery = null,
        AbstractQuery $userFilter = null,
        Query $masterQuery = null
    ) {
        parent::__construct($extensions);

        $this->searchable = $searchable;
        $this->userSubQuery = $userSubQuery;
        $this->userFilter = $userFilter;
        $this->masterQuery = $masterQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function initResult()
    {
        $this->subQueries = new BoolQuery();
        $this->filters = new BoolQuery();
        $this->query = ($this->masterQuery === null) ? new Query() : $this->masterQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult($fields, $from, $limit)
    {
        if ($this->userFilter !== null) {
            $this->filters->addMust($this->userFilter);
        }

        foreach ($fields as $field) {
            if (!$field instanceof ElasticaFieldInterface) {
                throw new \RuntimeException(
                    sprintf('All fields must be instances of \FSi\Component\DataSource\Driver\Elastica\ElasticaFieldInterface')
                );
            }

            $field->buildQuery($this->subQueries, $this->filters);
        }

        if ($this->userSubQuery !== null) {
            $this->subQueries->addMust($this->userSubQuery);
        }

        if ($this->subQueries->hasParam('should') || $this->subQueries->hasParam('must') ||
            $this->subQueries->hasParam('must_not')) {
            $this->query->setQuery($this->subQueries);
        }

        $tempFilters = $this->filters->getParams();
        if (!empty($tempFilters)) {
            $this->query->setPostFilter($this->filters);
        }

        if ($from > 0) {
            $this->query->setFrom($from);
        }
        if ($limit > 0) {
            $this->query->setSize($limit);
        }

        $resultSet = $this->searchable->search($this->query);

        return $resultSet;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'elastica';
    }

    /**
     * @return \Elastica\Query
     */
    public function getQuery()
    {
        if (!$this->query) {
            throw new \RuntimeException('Query is accessible only during preGetResult event.');
        }

        return $this->query;
    }
}
