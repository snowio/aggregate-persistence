<?php
namespace AggregatePersistence\Serialization;

class NormalizationResult
{
    private $normalizedAggregate;
    private $metadata;

    public function __construct(array $normalizedAggregate, array $metadata = [])
    {
        $this->normalizedAggregate = $normalizedAggregate;
        $this->metadata = $metadata;
    }

    public function normalizedAggregate() : array
    {
        return $this->normalizedAggregate;
    }

    public function metadata() : array
    {
        return $this->metadata;
    }
}
