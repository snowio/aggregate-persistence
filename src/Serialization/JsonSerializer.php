<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

/**
 * @internal
 */
class JsonSerializer implements Serializer
{
    private $normalizer;

    public function __construct(Normalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function serialize($aggregate, AggregateIdentifier $aggregateIdentifier) : SerializationResult
    {
        $normalizationResult = $this->normalizer->normalize($aggregate, $aggregateIdentifier);

        return new SerializationResult(
            json_encode($normalizationResult->normalizedAggregate(), JSON_PRETTY_PRINT),
            json_encode($normalizationResult->metadata(), JSON_PRETTY_PRINT)
        );
    }

    public function deserialize(SerializationResult $serializationResult, AggregateRepository $aggregateRepository)
    {
        $normalizationResult = new NormalizationResult(
            json_decode($serializationResult->serializedAggregate(), true),
            json_decode($serializationResult->serializedMetadata(), true)
        );

        return $this->normalizer->denormalize($normalizationResult, $aggregateRepository);
    }

    public function getAggregateClass(string $serializedMetadata) : string
    {
        $metadata = json_decode($serializedMetadata, true);

        return $this->normalizer->getAggregateClass($metadata);
    }
}
