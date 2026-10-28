# Change Log

## [Unreleased]
### Added
- loadViews() - structure loaded by getStructure() contains the names of views (materialized views from `system_schema.views`), needed by `viewExists()` in migrations

### Changed
- requires `efabrica/phoenix-core` ^4.1 (`StructureBehavior::loadViews()`)

## [1.0.1] - 2026-10-27
### Fixed
- `ScyllaAdapterFactory` keeps the `Cassandra\Cluster` object alive for the lifetime of the adapter. The ext-cassandra `Session` holds raw pointers into its `Cluster`; with `build()->connect()` the Cluster was destroyed immediately and every `prepare()` read freed memory, which showed up as intermittent `zend_mm_heap corrupted` crashes of `phoenix migrate`

### Added
- `ScyllaAdapter::__construct()` accepts an optional 4th argument `?Cassandra\Cluster $cluster` and exposes it via `getCluster()`

### Changed
- PHPUnit workflow runs on the official `php` docker images (8.4, 8.5, 8.6-rc) and builds the cassandra extension with install-php-extensions, PHP 8.6 added to the matrix
- phpunit.xml.dist migrated to the PHPUnit 9 configuration schema (`<coverage>` instead of the deprecated `<filter><whitelist>`), the "deprecated schema" warning is gone

## [1.0.0] - 2026-10-23
### Added
- adapters extracted from efabrica/phoenix-core into this package
- `keyspace` configuration option, `db_name` stays accepted as an alias

[unreleased]: https://github.com/efabrica-team/phoenix-scylla/compare/1.0.1...HEAD
[1.0.1]: https://github.com/efabrica-team/phoenix-scylla/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/efabrica-team/phoenix-scylla/compare/6122388980150f7749c198fbce3a608331291ca9...1.0.0
