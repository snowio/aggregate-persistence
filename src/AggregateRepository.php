<?php
namespace AggregatePersistence;

use AggregatePersistence\Exception\AggregateNotFoundException;

interface AggregateRepository
{
    /**
     * @return object
     * @throws AggregateNotFoundException
     */
    public function find(string $aggregateRootId);
}
