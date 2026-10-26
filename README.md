# Phoenix Cassandra / ScyllaDB adapter

Cassandra / ScyllaDB (CQL) adapter for [Phoenix](https://github.com/efabrica-team/phoenix-core) database migrations. It registers the `cassandra` adapter.

## Requirements
- PHP 8.4+
- the `cassandra` PHP extension: the maintained [he4rt/scylladb-php-driver](https://github.com/he4rt/scylladb-php-driver) fork of the DataStax PHP driver, it is not available through PECL and has to be built for your PHP version. On the official `php` docker images `install-php-extensions cassandra` from [docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer) builds it (this is how `.github/workflows/phpunit.yml` gets it)
- Cassandra 3+ or ScyllaDB (ScyllaDB 5.4 is covered by the test suite, see `.github/workflows/phpunit.yml`)

## Installation
```
composer require efabrica/phoenix-core efabrica/phoenix-scylla
```
The package registers the `cassandra` adapter in `Phoenix\Database\Adapter\AdapterFactory` from its composer bootstrap, nothing else has to be wired up. Composer refuses to install it without the extension, on a machine where the extension is missing (e.g. CI without a cluster) use `--ignore-platform-req=ext-cassandra`.

## Setup in a project
1. Create the keyspace. Phoenix does not create it and the adapter refuses to connect without one:
    ```cql
    CREATE KEYSPACE my_app WITH replication = {'class': 'SimpleStrategy', 'replication_factor': 1};
    ```
2. Create a configuration file `phoenix.php` (or `phoenix.yml`, `phoenix.neon`, `phoenix.json`) in the project root:
    ```php
    <?php

    return [
        'migration_dirs' => [
            'app' => __DIR__ . '/migrations',
        ],
        'environments' => [
            'local' => [
                'adapter' => 'cassandra',
                'host' => '127.0.0.1',
                'keyspace' => 'my_app',
            ],
            'production' => [
                'adapter' => 'cassandra',
                'host' => 'node1.internal,node2.internal,node3.internal',
                'port' => 9042,
                'keyspace' => 'my_app',
                'username' => 'migrator',
                'password' => getenv('DB_PASSWORD'),
                'consistency' => 'local_quorum',
                'datacenter' => 'dc1',
                'ssl' => true,
                'ssl_trusted_certs' => '/etc/ssl/certs/cluster-ca.pem',
            ],
        ],
        'default_environment' => 'local',
        'log_table_name' => 'phoenix_log',
    ];
    ```
    `yml` and `neon` config files need `symfony/yaml` resp. `nette/neon` installed. All configuration keys are described in the [configuration docs](https://github.com/efabrica-team/phoenix-core/blob/master/docs/configuration/index.md).
3. Create and run migrations with the command runner `vendor/bin/phoenix`:
    ```
    vendor/bin/phoenix create "App\CreateEvents"
    vendor/bin/phoenix migrate
    vendor/bin/phoenix status
    vendor/bin/phoenix rollback
    ```
    Use `-e production` to pick another environment and `-c path/to/config.php` for a config file elsewhere. The log table (`log_table_name`) is created automatically on the first run. All commands are described in the [commands docs](https://github.com/efabrica-team/phoenix-core/blob/master/docs/commands/index.md), how to write migrations in the [migrations docs](https://github.com/efabrica-team/phoenix-core/blob/master/docs/migrations/index.md).
4. The role used for migrations needs `CREATE`, `ALTER` and `DROP` on the keyspace plus `MODIFY` and `SELECT` on the log table and on the tables you fill with data.

## Configuration options
| key | required | description |
|---|---|---|
| `adapter` | yes | `cassandra` |
| `keyspace` | yes | keyspace, it has to exist. `db_name` is accepted as an alias, so a configuration can have the same shape for every adapter |
| `host` | no | one or more contact points separated by comma, default `localhost` |
| `port` | no | CQL port, default `9042` |
| `username`, `password` | no | credentials, both have to be set to enable authentication |
| `consistency` | no | default consistency level, one of the `Cassandra::CONSISTENCY_*` names in lower or upper case, e.g. `local_quorum`, `quorum`, `one`, `all` |
| `page_size` | no | default page size of selects |
| `connect_timeout`, `request_timeout` | no | seconds (float) |
| `datacenter` | no | local datacenter, enables datacenter aware round robin load balancing |
| `ssl` | no | `true` enables TLS |
| `ssl_trusted_certs` | no | path to the CA certificate used to verify the nodes (with `ssl`) |
| `version` | no | server version, read from `system.local` when not set. Currently informational only |
| `charset`, `collation` | no | accepted for compatibility with the other adapters, CQL has no charsets |

There is no `dsn`.

## What the adapter supports
- tables with comment, a required primary key made of a partition key and clustering columns with their order, dropping and truncating tables, copying a table (structure is rebuilt from the loaded one, data is read and inserted back row by row)
- adding and dropping columns, changing the type of a column (where CQL allows it), renaming primary key columns
- secondary indexes over exactly one column (`CREATE INDEX`)
- **materialized views** through `view()`, `replaceView()` and `dropView()`. The sql you pass is everything after `AS`, including the `PRIMARY KEY` part. `replaceView()` runs a drop followed by a create
- CQL specific column types on top of the common ones: `ascii`, `varint`, `counter`, `inet`, `timeuuid`, `duration`, `list`, `map`, `tuple`, and `set` as a real CQL set. Element types of collections come from the `values` setting
- `dump` and `diff` read the structure back from `system_schema`
- the migration log table is keyed by `(migration_datetime, classname)` instead of an autoincrement id (`LogTablePrimaryKeyInterface`)

### Primary keys
Every CQL table needs a primary key. The first primary column is the partition key, the rest are clustering columns:
```php
$this->table('events', ['tenant_id', 'created_at'])
```
A composite partition key is written as one primary column wrapped in brackets, and a clustering column may carry its order:
```php
$this->table('events', ['(tenant_id, day)', 'created_at DESC', 'id'])
```
This produces `PRIMARY KEY ((tenant_id, day), created_at, id) WITH CLUSTERING ORDER BY (created_at DESC, id ASC)`.

### Column type mapping
| Phoenix type | CQL type | note |
|---|---|---|
| `string`, `char`, `tinytext`, `text`, `mediumtext`, `longtext`, `json`, `enum` | `text` | enum values are not enforced |
| `ascii` | `ascii` | |
| `tinyinteger`, `smallinteger`, `mediuminteger`, `integer`, `biginteger` | `tinyint`, `smallint`, `int`, `int`, `bigint` | |
| `year` | `smallint` | |
| `bit`, `varint` | `varint` | |
| `counter` | `counter` | counter tables have their own rules in CQL |
| `numeric`, `decimal` | `decimal` | pass a `Cassandra\Decimal` instance as value |
| `float`, `double` | `float`, `double` | |
| `boolean` | `boolean` | |
| `binary`, `varbinary`, `tinyblob`, `blob`, `mediumblob`, `longblob` | `blob` | |
| `date`, `time` | `date`, `time` | pass a `Cassandra\Time` instance for `time` |
| `datetime`, `timestamp`, `timestamptz` | `timestamp` | |
| `duration` | `duration` | pass a `Cassandra\Duration` instance |
| `uuid`, `timeuuid` | `uuid`, `timeuuid` | |
| `inet` | `inet` | |
| `set`, `list`, `map`, `tuple` | `set<...>`, `list<...>`, `map<...,...>`, `tuple<...>` | element types from `values`, e.g. `['values' => ['text', 'int']]` for a map |
| `point`, `line`, `polygon` | `text` | no geometry in CQL |

```php
$table->addColumn('tags', Column::TYPE_SET, ['values' => ['text']]);        // set<text>
$table->addColumn('scores', Column::TYPE_LIST, ['values' => ['int']]);      // list<int>
$table->addColumn('meta', Column::TYPE_MAP, ['values' => ['text', 'int']]); // map<text,int>
```

## Limitations and things to watch
These are rejected with an explanatory exception instead of producing a query which would fail on the server:
- autoincrement columns (use `uuid` or `timeuuid`), column default values
- foreign keys, unique constraints, `UNIQUE` and `FULLTEXT` indexes
- a secondary index over more than one column
- renaming a table, renaming a non primary key column, changing name and type of a column in one step
- changing the primary key of an existing table (`addPrimary()` / `addPrimaryColumns()` in `alter`), a table without a primary key
- `LIMIT` with `OFFSET`, `OFFSET` does not exist in CQL (this also stops `copyTable()` from copying data in chunks)

Further differences worth knowing:
- **There are no transactions.** A migration on Cassandra is not atomic, a failing migration leaves the already executed statements applied. Keep migrations small and make them safe to re-run.
- **Schema changes are eventually consistent across the cluster.** After `CREATE TABLE` the other nodes may need a moment before they see it. The driver waits for schema agreement, but on a struggling cluster a following statement can still fail, re-running the migration usually helps.
- **`fetchAll()` ordering.** The server can only order by clustering columns, ordering by any other column is done in PHP after loading all matching rows, and `LIMIT` is applied after that sorting. `WHERE` conditions follow CQL rules: equality on partition key columns, ranges only on clustering columns, no comparison to `NULL`, no `!=`.
- **Values with no lossless PHP representation.** `decimal`, `varint`, `time` and `duration` columns need `Cassandra\Decimal`, `Cassandra\Varint`, `Cassandra\Time` resp. `Cassandra\Duration` instances when you insert or update through the adapter.
- **`dump` and `diff` do not write the primary key** into the generated migration (a pre-existing limitation of `Table::toMigrationTable()` shared by all adapters). On Cassandra a table cannot be created without one, so add the primary key to the generated migration by hand.
- **Materialized views** are experimental in Cassandra (disabled by default since 4.0, `enable_materialized_views`) and have restrictions in ScyllaDB. The adapter only generates the statements.
- `consistency` applies to every statement the adapter runs, including the log table writes. `ALL` on a cluster with a node down blocks migrations.

## Tests
Tests need a running Cassandra or ScyllaDB, connection is taken from the `PHOENIX_CASSANDRA_*` environment variables (see `phpunit.xml.dist`). The keyspace is dropped and recreated by the tests. The GitHub workflow runs them on the official `php:8.4-cli`, `php:8.5-cli` and `php:8.6-rc-cli` images against a ScyllaDB service container.
