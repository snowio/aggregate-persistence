<?php
namespace AggregatePersistence\Doctrine;

use AggregatePersistence\AggregateManager;
use AggregatePersistence\Serialization\SerializationResult;
use AggregatePersistence\Serialization\Serializer;
use Doctrine\Common\Persistence\ObjectManager;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\VirtualProxyInterface;

/**
 * @internal
 */
class DoctrineAggregateManager implements AggregateManager
{
    private $objectManager;
    private $proxyFactory;
    private $serializer;
    private $aggregateContainerSet;
    private $containerClassMap;
    private $containerClasses;

    /**
     * @param array $containerClassMap Specify custom aggregate container classes.
     *                                 Keys are aggregate classes, values are container classes
     */
    public function __construct(
        ObjectManager $objectManager,
        LazyLoadingValueHolderFactory $proxyFactory,
        Serializer $serializer,
        AggregateContainerSet $aggregateContainerSet,
        array $containerClassMap = []
    ) {
        $this->objectManager = $objectManager;
        $this->proxyFactory = $proxyFactory;
        $this->serializer = $serializer;
        $this->aggregateContainerSet = $aggregateContainerSet;
        $this->containerClassMap = $containerClassMap;
        $this->containerClasses = array_merge([GenericAggregateContainer::class], array_values($containerClassMap));
    }

    public function find(string $aggregateRootId)
    {
        return $this->aggregateContainerSet->getAggregate($aggregateRootId, function (string $aggregateRootId) {
            foreach ($this->containerClasses as $containerClass) {
                if ($container = $this->objectManager->find($containerClass, $aggregateRootId)) {
                    $aggregateProxy = $this->createProxy($container);
                    $container->setAggregate($aggregateProxy);
                    return $container;
                }
            }
        });
    }

    public function persist(string $aggregateRootId, $aggregate)
    {
        $containerClass = GenericAggregateContainer::class;

        foreach ($this->containerClassMap as $aggregateClass => $_containerClass) {
            if ($aggregate instanceof $aggregateClass) {
                $containerClass = $_containerClass;
                break;
            }
        }

        $serializationResult = $this->serializer->serialize($aggregate, $this->aggregateContainerSet);
        $container = new $containerClass($aggregateRootId, $aggregate, $serializationResult);
        $this->aggregateContainerSet->add($container);
        $this->objectManager->persist($container);
    }

    public function clear()
    {
        foreach ($this->containerClasses as $containerClass) {
            $this->objectManager->clear($containerClass);
        }

        $this->aggregateContainerSet->clear();
    }

    public function flush()
    {
        /** @var AggregateContainer $container */
        foreach ($this->aggregateContainerSet as $container) {
            $aggregate = $container->aggregate();
            if ($aggregate instanceof VirtualProxyInterface) {
                if (!$aggregate->isProxyInitialized()) {
                    continue;
                }
                $aggregate = $aggregate->getWrappedValueHolderValue();
            }
            $serializationResult = $this->serializer->serialize($aggregate, $this->aggregateContainerSet);
            $container->updateSerialization($serializationResult);
        }

        $this->objectManager->flush();
    }

    private function createProxy(AggregateContainer $aggregateContainer)
    {
        $serializedMetadata = $aggregateContainer->serializedMetadata();
        $aggregateClassName = $this->serializer->getAggregateClass($serializedMetadata);

        return $this->proxyFactory->createProxy(
            $aggregateClassName,
            function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($aggregateContainer) {
                $serializationResult = new SerializationResult(
                    $aggregateContainer->serializedAggregate(),
                    $aggregateContainer->serializedMetadata()
                );
                $wrappedObject = $this->serializer->deserialize($serializationResult, $this);
                $initializer = null; // turning off further lazy initialization
                $aggregateContainer->setAggregate($wrappedObject);
            }
        );
    }
}
