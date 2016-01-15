<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

/**
 * @internal
 */
interface Normalizer
{
    /**
     * @param object $aggregate
     */
    public function normalize($aggregate, AggregateIdentifier $aggregateIdentifier) : NormalizationResult;

    /**
     * @return object
     */
    public function denormalize(NormalizationResult $normalizationResult, AggregateRepository $aggregateRepository);

    public function getAggregateClass(array $metadata) : string;
}
