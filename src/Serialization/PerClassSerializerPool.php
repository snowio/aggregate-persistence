<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

class PerClassSerializerPool implements Serializer
{
    private $defaultSerializer;
    private $serializers;
    private $serializerCache = [];

    public function __construct(Serializer $defaultSerializer, array $serializers)
    {
        $this->defaultSerializer = $defaultSerializer;
        $this->serializers = $serializers;
    }

    public function serialize($aggregate, AggregateIdentifier $aggregateIdentifier) : SerializationResult
    {
        $aggregatRootClass = get_class($aggregate);
        $serializer = $this->getSerializer($aggregatRootClass);
        $serializationResult = $serializer->serialize($aggregate, $aggregateIdentifier);
        $metadataWithAggregateRootClass = $aggregatRootClass . ':' . $serializationResult->serializedMetadata();
        $resultWithAggregateRootClass = new SerializationResult($serializationResult->serializedAggregate(), $metadataWithAggregateRootClass);

        return $resultWithAggregateRootClass;
    }

    /**
     * @return object
     */
    public function deserialize(SerializationResult $resultWithAggregateRootClass, AggregateRepository $aggregateRepository)
    {
        list($aggregateRootClass, $originalSerializedMetadata) = explode(':', $resultWithAggregateRootClass->serializedMetadata(), 2);
        $serializer = $this->getSerializer($aggregateRootClass);
        $originalSerializationResult = new SerializationResult(
            $resultWithAggregateRootClass->serializedAggregate(),
            $originalSerializedMetadata
        );
        $aggregate = $serializer->deserialize($originalSerializationResult, $aggregateRepository);

        return $aggregate;
    }

    public function getAggregateClass(string $metadataWithAggregateRootClass) : string
    {
        list($aggregateRootClass, $metadata) = explode(':', $metadataWithAggregateRootClass, 2);
        $serializer = $this->getSerializer($aggregateRootClass);
        $newAggregateRootClass = $serializer->getAggregateClass($metadata);

        return $newAggregateRootClass;
    }

    private function getSerializer(string $aggregateRootClass) : Serializer
    {
        if (!isset($this->serializerCache[$aggregateRootClass])) {
            $this->serializerCache[$aggregateRootClass] = $this->chooseBestSerializer($aggregateRootClass);
        }

        return $this->serializerCache[$aggregateRootClass];
    }

    private function chooseBestSerializer(string $aggregateRootClass) : Serializer
    {
        if (isset($this->serializers[$aggregateRootClass])) {
            return $this->serializers[$aggregateRootClass];
        }

        foreach ($this->serializers as $class => $serializer) {
            if (is_subclass_of($aggregateRootClass, $class)) {
                return $serializer;
            }
        }

        return $this->defaultSerializer;
    }
}
