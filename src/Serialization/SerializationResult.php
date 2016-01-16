<?php
namespace AggregatePersistence\Serialization;

class SerializationResult
{
    private $serializedAggregate;
    private $serializedMetadata;

    public function __construct(string $serializedAggregate, string $serializedMetadata = '')
    {
        $this->serializedAggregate = $serializedAggregate;
        $this->serializedMetadata = $serializedMetadata;
    }

    public function serializedAggregate() : string
    {
        return $this->serializedAggregate;
    }

    public function serializedMetadata() : string
    {
        return $this->serializedMetadata;
    }
}
