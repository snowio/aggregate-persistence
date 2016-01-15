<?php
class SerializationTest extends \PHPUnit_Framework_TestCase
{
    public function testOneAggregate()
    {
        $josh = new User('2131293jq', 'Josh Di Fabio');
        $catalog = new Catalog('uk2015', 'UK 2015', $josh);
        $catalog->addProduct(new Product('S9123', 'T-Shirt'));
        $catalog->addProduct(new Product('S9124', 'Jumper'));

        $normalizer = new \AggregatePersistence\Serialization\GenericNormalizer();
        $serializer = new \AggregatePersistence\Serialization\JsonSerializer($normalizer);

        $aggregateContainerSet = new \AggregatePersistence\Doctrine\AggregateContainerSet;
        $aggregateContainerSet->add(new \AggregatePersistence\Doctrine\AggregateContainer($catalog->id(), $catalog));
        $aggregateContainerSet->add(new \AggregatePersistence\Doctrine\AggregateContainer($josh->id(), $josh));

        $serialization1 = $serializer->serialize($catalog, $aggregateContainerSet);

        $repository = new class ([$josh->id() => $josh]) implements \AggregatePersistence\AggregateRepository {
            private $aggregates;

            public function __construct(array $aggregates)
            {
                $this->aggregates = $aggregates;
            }

            public function find(string $aggregateRootId)
            {
                return $this->aggregates[$aggregateRootId];
            }
        };

        $deserialization = $serializer->deserialize($serialization1, $repository);

        $serialization2 = $serializer->serialize($deserialization, $aggregateContainerSet);

        $this->assertSame($serialization1, $serialization2);
    }
}

class Catalog
{
    private $id;
    private $name;
    private $creator;
    private $products = [];

    public function __construct(string $id, string $name, User $creator)
    {
        $this->id = $id;
        $this->name = $name;
        $this->creator = $creator;
    }

    public function id() : string
    {
        return $this->id;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function creator() : User
    {
        return $this->creator;
    }

    public function addProduct(Product $product)
    {
        $this->products[] = $product;
    }

    public function products()
    {
        return $this->products;
    }
}

class Product
{
    private $sku;
    private $name;

    public function __construct(string $sku, string $name)
    {
        $this->sku = $sku;
        $this->name = $name;
    }

    public function sku() : string
    {
        return $this->sku;
    }

    public function name() : string
    {
        return $this->name;
    }
}

class User
{
    private $id;
    private $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function id() : string
    {
        return $this->id;
    }

    public function name() : string
    {
        return $this->name;
    }
}
