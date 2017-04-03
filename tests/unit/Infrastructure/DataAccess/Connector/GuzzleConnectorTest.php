<?php

namespace Honeybee\Tests\Infrastructure\DataAccess\Connector;

use Honeybee\Infrastructure\Config\ConfigInterface;
use Honeybee\Infrastructure\DataAccess\Connector\GuzzleConnector;

class GuzzleConnectorTest extends ConnectorInterfaceTest
{
    protected function getConnector($name, ConfigInterface $config)
    {
        return new GuzzleConnector($name, $config);
    }
}
