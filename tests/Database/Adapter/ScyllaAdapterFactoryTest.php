<?php

declare(strict_types=1);

namespace Phoenix\Tests\Database\Adapter;

use Phoenix\Config\EnvironmentConfig;
use Phoenix\Database\Adapter\AdapterFactory;
use Phoenix\Database\Adapter\ScyllaAdapter;
use Phoenix\Database\QueryBuilder\ScyllaQueryBuilder;
use Phoenix\Exception\InvalidArgumentValueException;
use Phoenix\Tests\Helpers\Adapter\ScyllaCleanupAdapter;
use Phoenix\Tests\Helpers\Scylla\ScyllaSessionFactory;
use PHPUnit\Framework\TestCase;

final class ScyllaAdapterFactoryTest extends TestCase
{
    public function testIsRegisteredByBootstrap(): void
    {
        $this->assertContains('cassandra', AdapterFactory::getAdapterNames());
    }

    public function testScylla(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'cassandra',
            'host' => ScyllaSessionFactory::host(),
            'port' => ScyllaSessionFactory::port(),
            'keyspace' => ScyllaSessionFactory::keyspace(),
        ]);
        (new ScyllaCleanupAdapter(ScyllaSessionFactory::keyspace()))->cleanupDatabase();

        $adapter = AdapterFactory::instance($config);
        $this->assertInstanceOf(ScyllaAdapter::class, $adapter);
        $this->assertInstanceOf(ScyllaQueryBuilder::class, $adapter->getQueryBuilder());
        $this->assertSame(ScyllaSessionFactory::keyspace(), $adapter->getKeyspace());
        $this->assertNotNull($adapter->getVersion());
    }

    public function testDbNameIsAcceptedAsKeyspace(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'cassandra',
            'host' => ScyllaSessionFactory::host(),
            'port' => ScyllaSessionFactory::port(),
            'db_name' => ScyllaSessionFactory::keyspace(),
        ]);
        (new ScyllaCleanupAdapter(ScyllaSessionFactory::keyspace()))->cleanupDatabase();

        $adapter = AdapterFactory::instance($config);
        $this->assertSame(ScyllaSessionFactory::keyspace(), $adapter->getKeyspace());
    }

    public function testKeyspaceWinsOverDbName(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'cassandra',
            'host' => ScyllaSessionFactory::host(),
            'port' => ScyllaSessionFactory::port(),
            'keyspace' => ScyllaSessionFactory::keyspace(),
            'db_name' => 'some_other_keyspace',
        ]);
        (new ScyllaCleanupAdapter(ScyllaSessionFactory::keyspace()))->cleanupDatabase();

        $adapter = AdapterFactory::instance($config);
        $this->assertSame(ScyllaSessionFactory::keyspace(), $adapter->getKeyspace());
    }

    public function testScyllaWithoutKeyspace(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Adapter "cassandra" requires "keyspace" (or "db_name") to be set.');
        AdapterFactory::instance(new EnvironmentConfig(['adapter' => 'cassandra']));
    }

    public function testUnknown(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Unknown adapter "unknown".');
        AdapterFactory::instance(new EnvironmentConfig(['adapter' => 'unknown']));
    }
}
