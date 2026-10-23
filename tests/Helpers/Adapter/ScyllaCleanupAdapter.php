<?php

declare(strict_types=1);

namespace Phoenix\Tests\Helpers\Adapter;

use Cassandra\SimpleStatement;
use Phoenix\Tests\Helpers\Scylla\ScyllaSessionFactory;

final class ScyllaCleanupAdapter implements CleanupInterface
{
    private string $keyspace;

    public function __construct(?string $keyspace = null)
    {
        $this->keyspace = $keyspace ?? ScyllaSessionFactory::keyspace();
    }

    public function cleanupDatabase(): void
    {
        $session = ScyllaSessionFactory::create();
        $session->execute(new SimpleStatement(sprintf('DROP KEYSPACE IF EXISTS %s', $this->keyspace)));
        $session->execute(new SimpleStatement(sprintf(
            "CREATE KEYSPACE %s WITH replication = {'class': 'SimpleStrategy', 'replication_factor': 1}",
            $this->keyspace
        )));
    }
}
