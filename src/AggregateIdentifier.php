<?php
namespace AggregatePersistence;

interface AggregateIdentifier
{
    /**
     * @return null|string
     */
    public function getAggregateRootId($object);
}
