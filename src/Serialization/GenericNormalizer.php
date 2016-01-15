<?php
namespace AggregatePersistence\Serialization;

use AggregatePersistence\AggregateIdentifier;
use AggregatePersistence\AggregateRepository;

/**
 * @internal
 */
class GenericNormalizer implements Normalizer
{
    private static $classReflections = [];
    private static $propertyReflections = [];

    private $ignoredValueIndicator;

    public function __construct()
    {
        $this->ignoredValueIndicator = new \stdClass;
    }

    public function normalize($aggregate, AggregateIdentifier $aggregateIdentifier) : NormalizationResult
    {
        if (!is_object($aggregate)) {
            throw new \InvalidArgumentException;
        }

        $scope = (object)[
            'objects' => [],
            'objectIds' => [],
            'aggregateIdentifier' => $aggregateIdentifier,
        ];

        $result = $this->normalizeObject($aggregate, $scope);
        $result['#objects'] = $scope->objects;

        return new NormalizationResult($result, ['class' => get_class($aggregate)]);
    }

    public function denormalize(NormalizationResult $normalizationResult, AggregateRepository $aggregateRepository)
    {
        $normalizedAggregate = $normalizationResult->normalizedAggregate();

        $scope = (object)[
            'objects' => [],
            'aggregateRepository' => $aggregateRepository,
        ];

        foreach ($normalizedAggregate['#objects'] as $objectId => $normalizedObject) {
            $className = $normalizedObject['#class'];
            $scope->objects[$objectId] = self::getClassReflection($className)->newInstanceWithoutConstructor();
        }

        $aggregateClassName = $normalizedAggregate['#class'];
        $aggregate = self::getClassReflection($aggregateClassName)->newInstanceWithoutConstructor();

        foreach ($scope->objects as $objectId => $object) {
            $normalizedObject = $normalizedAggregate['#objects'][$objectId];
            $this->hydrateObject($normalizedObject, $object, $scope);
        }

        $this->hydrateObject($normalizedAggregate, $aggregate, $scope);

        return $aggregate;
    }

    public function getAggregateClass(array $metadata) : string
    {
        return $metadata['class'];
    }

    private function normalizeValue($value, \stdClass $scope)
    {
        if (is_resource($value)) {
            return $this->ignoredValueIndicator;
        }

        if (null === $value) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->normalizeArray($value, $scope);
        }

        // value is an object

        return $this->getObjectReference($value, $scope);
    }

    private function denormalizeValue($normalizedValue, \stdClass $scope)
    {
        if (null === $normalizedValue) {
            return null;
        }

        if (is_scalar($normalizedValue)) {
            return $normalizedValue;
        }

        if (!is_array($normalizedValue)) {
            throw new \InvalidArgumentException;
        }

        if (1 === count($normalizedValue)) {
            if (isset($normalizedValue['#aggregateRootId'])) {
                return $scope->aggregateRepository->find($normalizedValue['#aggregateRootId']);
            }

            if (isset($normalizedValue['#objectId'])) {
                return $scope->objects[$normalizedValue['#objectId']];
            }

            if (isset($normalizedValue['#serviceId'])) {
                return ServiceRegistrar::getService($normalizedValue['#serviceId']);
            }
        }

        return $this->denormalizeArray($normalizedValue, $scope);
    }

    private function getObjectReference($object, \stdClass $scope) : array
    {
        if ($object instanceof \Closure) {
            return $this->ignoredValueIndicator;
        }

        $aggregateRootId = $scope->aggregateIdentifier->getAggregateRootId($object);

        if (null !== $aggregateRootId) {
            return ['#aggregateRootId' => $aggregateRootId];
        }

        $objectHash = spl_object_hash($object);

        if (isset($scope->objectIds[$objectHash])) {
            $objectId = $scope->objectIds[$objectHash];
        } elseif (null !== $serviceId = ServiceRegistrar::getServiceId($object)) {
            return ['#serviceId' => $serviceId];
        } else {
            $objectId = 'object:' . (1 + count($scope->objects));
            $scope->objectIds[$objectHash] = $objectId;
            $scope->objects[$objectId] = null;
            $scope->objects[$objectId] = $this->normalizeObject($object, $scope);
        }

        return ['#objectId' => $objectId];
    }

    private function normalizeObject($object, \stdClass $scope) : array
    {
        if (get_class($object) === \stdClass::class) {
            return $this->normalizeStdClassInstance($object, $scope);
        }

        $className = get_class($object);
        $normalization = ['#class' => $className];

        foreach (self::getClassProperties($className) as $propertyName => $property) {
            $normalizedValue = $this->normalizeValue($property->getValue($object), $scope);
            if ($normalizedValue !== $this->ignoredValueIndicator) {
                $normalization[$propertyName] = $normalizedValue;
            }
        }

        return $normalization;
    }

    private function hydrateObject(array $normalizedValues, $object, \stdClass $scope)
    {
        if ($object instanceof \stdClass) {
            $this->hydrateStdClassInstance($normalizedValues, $object, $scope);
            return;
        }

        foreach (self::getClassProperties(get_class($object)) as $propertyName => $property) {
            if (!array_key_exists($propertyName, $normalizedValues)) {
                continue;
            }

            $value = $this->denormalizeValue($normalizedValues[$propertyName], $scope);
            $property->setValue($object, $value);
        }
    }

    private function normalizeStdClassInstance(\stdClass $object, \stdClass $scope) : array
    {
        $normalization = array_merge(
            ['#class' => \stdClass::class],
            $this->normalizeArray((array)$object, $scope)
        );

        return $normalization;
    }

    private function hydrateStdClassInstance(array $normalizedValues, \stdClass $object, \stdClass $scope)
    {
        foreach ($normalizedValues as $name => $normalizedValue) {
            $object->$name = $this->denormalizeValue($normalizedValue, $scope);
        }
    }

    private function normalizeArray(array $value, \stdClass $scope) : array
    {
        $normalized = [];

        foreach ($value as $name => $_value) {
            $normalizedValue = $this->normalizeValue($_value, $scope);
            if ($normalizedValue !== $this->ignoredValueIndicator) {
                $normalized[$name] = $normalizedValue;
            }
        }

        return $normalized;
    }

    private function denormalizeArray(array $normalizedValues, \stdClass $scope) : array
    {
        return array_map(function ($normalizedValue) use ($scope) {
            return $this->denormalizeValue($normalizedValue, $scope);
        }, $normalizedValues);
    }

    private static function getClassReflection($className) : \ReflectionClass
    {
        if (!isset(self::$classReflections[$className])) {
            self::$classReflections[$className] = new \ReflectionClass($className);
        }

        return self::$classReflections[$className];
    }

    /**
     * @return \ReflectionProperty[]
     */
    private static function getClassProperties($className) : array
    {
        if (!isset(self::$propertyReflections[$className])) {
            $properties = [];

            foreach (self::getClassReflection($className)->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $property->setAccessible(true);
                    $properties[$property->getName()] = $property;
                }
            }

            self::$propertyReflections[$className] = $properties;
        }

        return self::$propertyReflections[$className];
    }
}
