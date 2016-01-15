<?php
namespace AggregatePersistence;

use AggregatePersistence\Exception\AggregateIdCollisionException;
use AggregatePersistence\Exception\AggregatePersistedWithMultipleIdsException;

interface AggregateManager extends AggregateRepository
{
    /**
     * @param object $aggregate
     * @throws AggregateIdCollisionException
     * @throws AggregatePersistedWithMultipleIdsException
     */
    public function persist(string $aggregateRootId, $aggregate);

    public function clear();

    public function flush();
}
