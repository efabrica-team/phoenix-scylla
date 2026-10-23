<?php

declare(strict_types=1);

use Phoenix\Database\Adapter\AdapterFactory;
use Phoenix\Database\Adapter\ScyllaAdapterFactory;

// loaded by composer (autoload.files): makes the "cassandra" adapter available
AdapterFactory::registerFactory(new ScyllaAdapterFactory());
