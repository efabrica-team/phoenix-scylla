<?php

declare(strict_types=1);

namespace Phoenix\Database\Adapter;

use Cassandra;
use Cassandra\Cluster\Builder;
use Cassandra\SimpleStatement;
use Phoenix\Config\EnvironmentConfig;
use Phoenix\Exception\InvalidArgumentValueException;
use Throwable;

final class ScyllaAdapterFactory implements AdapterFactoryInterface
{
    public const string ADAPTER_CASSANDRA = 'cassandra';

    public function getAdapterName(): string
    {
        return self::ADAPTER_CASSANDRA;
    }

    public function create(EnvironmentConfig $config): ScyllaAdapter
    {
        if (!extension_loaded('cassandra')) {
            throw new InvalidArgumentValueException('Adapter "cassandra" requires the "cassandra" PHP extension (DataStax PHP driver) to be installed.');
        }
        // "keyspace" is the CQL name of the option, "db_name" is accepted so one config shape fits every adapter
        $keyspace = $config->getOption('keyspace') ?: $config->getDbName();
        if (!is_string($keyspace) || $keyspace === '') {
            throw new InvalidArgumentValueException('Adapter "cassandra" requires "keyspace" (or "db_name") to be set.');
        }

        /** @var Builder $builder */
        $builder = Cassandra::cluster();
        $builder->withContactPoints(...$config->getHosts());
        $builder->withPort($config->getPort(9042) ?? 9042);

        $username = $config->getUsername();
        $password = $config->getPassword();
        if ($username !== null && $password !== null) {
            $builder->withCredentials($username, $password);
        }

        $this->applyOptionalSettings($builder, $config);

        // the Cluster object must outlive the Session: ext-cassandra Session keeps raw pointers into it and
        // reads freed memory in every prepare() once the Cluster is destroyed (zend_mm_heap corrupted)
        $cluster = $builder->build();
        $session = $cluster->connect($keyspace);

        $version = $config->getVersion();
        if ($version === null) {
            $version = $this->loadVersion($session);
        }
        $adapter = new ScyllaAdapter($session, $keyspace, AdapterFactory::normalizeVersion($version), $cluster);
        $adapter->setCharset($config->getCharset());
        $adapter->setCollation($config->getCollation());
        return $adapter;
    }

    private function applyOptionalSettings(Builder $builder, EnvironmentConfig $config): void
    {
        $consistency = $config->getOption('consistency');
        if (is_string($consistency)) {
            $constant = 'Cassandra::CONSISTENCY_' . strtoupper($consistency);
            if (!defined($constant)) {
                throw new InvalidArgumentValueException('Unknown consistency level "' . $consistency . '".');
            }
            /** @var int $consistencyValue */
            $consistencyValue = constant($constant);
            $builder->withDefaultConsistency($consistencyValue);
        }

        $pageSize = $config->getOption('page_size');
        if (is_numeric($pageSize)) {
            $builder->withDefaultPageSize((int)$pageSize);
        }

        $connectTimeout = $config->getOption('connect_timeout');
        if (is_numeric($connectTimeout)) {
            $builder->withConnectTimeout((float)$connectTimeout);
        }

        $requestTimeout = $config->getOption('request_timeout');
        if (is_numeric($requestTimeout)) {
            $builder->withRequestTimeout((float)$requestTimeout);
        }

        $datacenter = $config->getOption('datacenter');
        if (is_string($datacenter) && $datacenter !== '') {
            $builder->withDatacenterAwareRoundRobinLoadBalancingPolicy($datacenter, 0, false);
        }

        if ($config->getOption('ssl')) {
            $sslBuilder = Cassandra::ssl();
            $trustedCerts = $config->getOption('ssl_trusted_certs');
            if (is_string($trustedCerts) && $trustedCerts !== '') {
                $sslBuilder->withTrustedCerts($trustedCerts);
            }
            $builder->withSSL($sslBuilder->build());
        }
    }

    /**
     * @param mixed $session
     */
    private function loadVersion($session): ?string
    {
        try {
            $rows = $session->execute(new SimpleStatement('SELECT release_version FROM system.local'));
            foreach ($rows as $row) {
                return (string)$row['release_version'];
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }
}
