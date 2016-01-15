<?php
namespace AggregatePersistence\Doctrine;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\Exception\AggregateNotFoundException;
use AggregatePersistence\Exception\AggregatePersistedWithMultipleIdsException;
use AggregatePersistence\Exception\AggregateIdCollisionException;
use ProxyManager\Proxy\VirtualProxyInterface;
use Snowball\Platform\Organisation\Implementation\User;

/**
 * @internal
 */
class AggregateContainerSet implements \IteratorAggregate, AggregateIdentifier
{
    /** @var AggregateContainer[] */
    private $containers = [];
    /** @var string[] */
    private $aggregateRootIds = [];

    public function add(AggregateContainer $container)
    {
        $aggregateRootId = $container->aggregateRootId();
        $aggregate = $container->aggregate();

        if (!isset($this->containers[$aggregateRootId])) {
            $objectHash = spl_object_hash($aggregate);

            if (isset($this->aggregateRootIds[$objectHash])) {
                throw new AggregatePersistedWithMultipleIdsException;
            }

            $this->containers[$aggregateRootId] = $container;
            $this->aggregateRootIds[$objectHash] = $aggregateRootId;

            $container->onSetAggregate(function ($newAggregateInstance) use ($aggregateRootId) {
                $newObjectHash = spl_object_hash($newAggregateInstance);
                $this->aggregateRootIds[$newObjectHash] = $aggregateRootId;
            });
        } elseif ($aggregate !== $this->containers[$aggregateRootId]->aggregate()) {
            throw new AggregateIdCollisionException;
        }
    }

    /**
     * @return object
     */
    public function getAggregate(string $aggregateRootId, callable $containerLocator = null)
    {
        return $this->getContainer($aggregateRootId, $containerLocator)->aggregate();
    }

    /**
     * @return null|string
     */
    public function getAggregateRootId($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException;
        }

        return $this->aggregateRootIds[spl_object_hash($object)] ?? null;
    }

    public function getIterator()
    {
        foreach ($this->containers as $container) {
            yield $container;
        }
    }

    public function clear()
    {
        $this->containers = [];
        $this->aggregateRootIds = [];
    }

    private function getContainer(string $aggregateRootId, callable $containerLocator = null) : AggregateContainer
    {
        if (!isset($this->containers[$aggregateRootId])) {
            if (!$containerLocator || (!$container = call_user_func($containerLocator, $aggregateRootId))) {
                throw new AggregateNotFoundException;
            }
            $this->add($container);
        }

        return $this->containers[$aggregateRootId];
    }
}
