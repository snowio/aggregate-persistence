<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

/**
 * @internal
 */
interface Serializer
{
    /**
     * @param object $aggregate
     */
    public function serialize($aggregate, AggregateIdentifier $aggregateIdentifier) : SerializationResult;

    /**
     * @return object
     */
    public function deserialize(SerializationResult $serializationResult, AggregateRepository $aggregateRepository);

    public function getAggregateClass(string $serializedMetadata) : string;
}
