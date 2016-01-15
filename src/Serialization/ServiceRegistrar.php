<?php
namespace AggregatePersistence\Serialization;

class ServiceRegistrar
{
    private static $servicesById = [];
    private static $serviceIds = [];

    public static function registerService($service, string ...$ids)
    {
        if (0 == count($ids)) {
            $ids = [get_class($service)];
        }

        foreach ($ids as $id) {
            if (isset(self::$servicesById[$id]) && $service !== self::$servicesById[$id]) {
                throw new \RuntimeException('Duplicate service ID.');
            }
        }

        $primaryId = $ids[0];
        $objectHash = spl_object_hash($service);

        if (isset(self::$serviceIds[$objectHash])) {
            if ($primaryId !== self::$serviceIds[$objectHash]) {
                throw new \RuntimeException('Duplicate service.');
            }
        } else {
            self::$serviceIds[$objectHash] = $primaryId;
        }

        foreach ($ids as $id) {
            self::$servicesById[$id] = $service;
        }
    }

    /**
     * @param object $object
     * @return null|string
     */
    public static function getServiceId($object)
    {
        $objectHash = spl_object_hash($object);

        if (isset(self::$serviceIds[$objectHash])) {
            return self::$serviceIds[$objectHash];
        }
    }

    /**
     * @return object
     */
    public static function getService(string $id)
    {
        if (!isset(self::$servicesById[$id])) {
            throw new \RuntimeException('No service exists with the specified ID.');
        }

        return self::$servicesById[$id];
    }
}
