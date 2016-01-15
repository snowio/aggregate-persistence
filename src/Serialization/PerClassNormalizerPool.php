<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

class PerClassNormalizerPool implements Normalizer
{
    private $defaultNormalizer;
    private $normalizers;
    private $normalizerCache = [];

    public function __construct(Normalizer $defaultNormalizer, array $normalizers)
    {
        $this->defaultNormalizer = $defaultNormalizer;
        $this->normalizers = $normalizers;
    }

    public function normalize($aggregate, AggregateIdentifier $aggregateIdentifier) : NormalizationResult
    {
        $aggregateClass = get_class($aggregate);
        $normalizer = $this->getNormalizer($aggregateClass);
        $normalizationResult = $normalizer->normalize($aggregate, $aggregateIdentifier);
        $wrappedNormalizationResult = new NormalizationResult(
            $normalizationResult->normalizedAggregate(),
            [
                'aggregateClass' => $aggregateClass,
                'metadata' => $normalizationResult->metadata(),
            ]
        );

        return $wrappedNormalizationResult;
    }

    /**
     * @return object
     */
    public function denormalize(NormalizationResult $wrappedNormalizationResult, AggregateRepository $aggregateRepository)
    {
        $wrappedMetadata = $wrappedNormalizationResult->metadata();
        $normalizer = $this->getNormalizer($wrappedMetadata['aggregateClass']);
        $unwrappedNormalizationResult = new NormalizationResult(
            $wrappedNormalizationResult->normalizedAggregate(),
            $wrappedMetadata['metadata']
        );
        $aggregate = $normalizer->denormalize($unwrappedNormalizationResult, $aggregateRepository);

        return $aggregate;
    }

    public function getAggregateClass(array $wrappedMetadata) : string
    {
        $normalizer = $this->getNormalizer($wrappedMetadata['aggregateClass']);
        $aggregateClass = $normalizer->getAggregateClass($wrappedMetadata['metadata']);;

        return $aggregateClass;
    }

    private function getNormalizer(string $aggregateClass) : Normalizer
    {
        if (!isset($this->normalizerCache[$aggregateClass])) {
            $this->normalizerCache[$aggregateClass] = $this->chooseBestNormalizer($aggregateClass);
        }

        return $this->normalizerCache[$aggregateClass];
    }

    private function chooseBestNormalizer(string $aggregateClass) : Normalizer
    {
        if (isset($this->normalizers[$aggregateClass])) {
            return $this->normalizers[$aggregateClass];
        }

        foreach ($this->normalizers as $class => $normalizer) {
            if (is_subclass_of($aggregateClass, $class)) {
                return $normalizer;
            }
        }

        return $this->defaultNormalizer;
    }
}
