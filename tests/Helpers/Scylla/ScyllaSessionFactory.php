<?php

declare(strict_types=1);

namespace Phoenix\Tests\Helpers\Scylla;

use Cassandra;
use Cassandra\Cluster;
use Cassandra\Session;
use Phoenix\Database\Adapter\ScyllaAdapter;

final class ScyllaSessionFactory
{
    public static function host(): string
    {
        return (string)(getenv('PHOENIX_CASSANDRA_HOST') ?: 'localhost');
    }

    public static function port(): int
    {
        return (int)(getenv('PHOENIX_CASSANDRA_PORT') ?: 9042);
    }

    public static function keyspace(): string
    {
        return (string)(getenv('PHOENIX_CASSANDRA_KEYSPACE') ?: 'phoenix');
    }

    public static function create(?string $keyspace = null): Session
    {
        $cluster = self::cluster();
        return $keyspace === null ? $cluster->connect('') : $cluster->connect($keyspace);
    }

    public static function cluster(): Cluster
    {
        $builder = Cassandra::cluster()
            ->withContactPoints(self::host())
            ->withPort(self::port());

        $username = getenv('PHOENIX_CASSANDRA_USERNAME');
        $password = getenv('PHOENIX_CASSANDRA_PASSWORD');
        if ($username !== false && $username !== '' && $password !== false) {
            $builder->withCredentials($username, (string)$password);
        }

        return $builder->build();
    }

    /**
     * Preferred over create(): the adapter keeps the Cluster alive as long as the Session
     * (ext-cassandra Session holds raw pointers into the Cluster, see ScyllaAdapterFactory)
     */
    public static function createAdapter(string $keyspace): ScyllaAdapter
    {
        $cluster = self::cluster();
        return new ScyllaAdapter($cluster->connect($keyspace), $keyspace, null, $cluster);
    }
}
