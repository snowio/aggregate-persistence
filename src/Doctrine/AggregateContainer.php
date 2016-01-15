<?php
namespace AggregatePersistence\Doctrine;

use AggregatePersistence\Serialization\SerializationResult;

abstract class AggregateContainer
{
    /**
     * @Id @Column(type="guid")
     */
    protected $aggregateRootId;
    protected $aggregate;
    /**
     * @Column(type="text", nullable=false)
     */
    protected $serializedAggregate;
    /**
     * @Column(type="text", nullable=false)
     */
    protected $serializedMetadata;

    private $aggregateSetObservers = [];

    /**
     * @param object $aggregate
     */
    public function __construct(string $aggregateRootId, $aggregate, SerializationResult $serializationResult)
    {
        $this->aggregateRootId = $aggregateRootId;
        $this->aggregate = $aggregate;
        $this->updateSerialization($serializationResult);
    }

    public function aggregateRootId() : string
    {
        return $this->aggregateRootId;
    }

    /**
     * @return object
     */
    public function aggregate()
    {
        if (!isset($this->aggregate)) {
            throw new \LogicException('No aggregate has been set.');
        }

        return $this->aggregate;
    }

    /**
     * @param object $aggregate
     */
    public function setAggregate($aggregate)
    {
        if (!is_object($aggregate)) {
            throw new \InvalidArgumentException;
        }

        if ($aggregate === $this->aggregate) {
            return;
        }

        $this->aggregate = $aggregate;

        foreach ($this->aggregateSetObservers as $observer) {
            call_user_func($observer, $aggregate);
        }
    }

    public function updateSerialization(SerializationResult $serializationResult)
    {
        $this->serializedAggregate = $serializationResult->serializedAggregate();
        $this->serializedMetadata = $serializationResult->serializedMetadata();
    }

    public function serializedAggregate() : string
    {
        if (!isset($this->serializedAggregate)) {
            throw new \LogicException('No serialization has been set.');
        }

        return $this->serializedAggregate;
    }

    public function serializedMetadata() : string
    {
        if (!isset($this->serializedMetadata)) {
            throw new \LogicException('No serialization metadata has been set.');
        }

        return $this->serializedMetadata;
    }

    public function onSetAggregate(callable $observer)
    {
        $this->aggregateSetObservers[] = $observer;
    }
}
