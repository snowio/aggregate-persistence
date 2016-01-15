<?php
namespace AggregatePersistence\Doctrine;

interface AggregateContainerFactory
{
    /**
     * @param object $aggregate
     */
    public function createAggregateContainer(string $aggregateRootId, $aggregate) : AggregateContainer;
}
