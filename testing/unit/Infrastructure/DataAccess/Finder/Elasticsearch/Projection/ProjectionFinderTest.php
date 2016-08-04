<?php

namespace Honeybee\Tests\DataAccess\Finder\Elasticsearch\Projection;

use Honeybee\Infrastructure\Config\ArrayConfig;
use Honeybee\Infrastructure\DataAccess\Connector\ConnectorInterface;
use Honeybee\Infrastructure\DataAccess\Finder\Elasticsearch\Projection\ProjectionFinder;
use Honeybee\Tests\Fixture\BookSchema\Projection\Book\BookType;
use Honeybee\Tests\TestCase;
use Psr\Log\NullLogger;
use Mockery;
use Workflux\StateMachine\StateMachineInterface;
use Honeybee\Infrastructure\DataAccess\Finder\FinderResult;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class ProjectionFinderTest extends TestCase
{
    protected $projection_type;

    protected $mock_connector;

    protected $mock_client;

    public function setUp()
    {
        $state_machine = Mockery::mock(StateMachineInterface::CLASS);
        $this->projection_type = new BookType($state_machine);
        $this->mock_connector = Mockery::mock(ConnectorInterface::CLASS);
        $this->mock_client = Mockery::mock(Client::CLASS);
    }

    public function testGetByIdentifier()
    {
        $test_data = include(__DIR__ . '/Fixture/projection_finder_test_01.php');
        $identifier = 'honeybee-cmf.projection_fixtures.book-a726301d-dbae-4fb6-91e9-a19188a17e71-de_DE-1';

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('get')->once()->with([
            'index' => 'index',
            'type' => 'type',
            'id' => $identifier
        ])->andReturn($test_data['raw_result']);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $projections = $this->createProjections([ $test_data['raw_result'] ]);
        $finder_result = new FinderResult($projections, 1);

        $this->assertEquals($finder_result, $projection_finder->getByIdentifier($identifier));
    }

    public function testGetByIdentifierMissing()
    {
        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('get')->once()->with([
            'index' => 'index',
            'type' => 'type',
            'key' => 'value',
            'id' => 'missing'
        ])->andThrow(Missing404Exception::CLASS);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([
                'index' => 'index',
                'type' => 'type',
                'parameters' => [ 'get' => [ 'key' => 'value' ] ]
            ]),
            new NullLogger,
            $this->projection_type
        );

        $finder_result = new FinderResult([], 0);

        $this->assertEquals($finder_result, $projection_finder->getByIdentifier('missing'));
    }

    public function testGetByIdentifiers()
    {
        $test_data = include(__DIR__ . '/Fixture/projection_finder_test_02.php');
        $identifiers = [
            'honeybee-cmf.projection_fixtures.book-a726301d-dbae-4fb6-91e9-a19188a17e71-de_DE-1',
            'honeybee-cmf.projection_fixtures.book-61d8da68-0d56-4b8b-b393-21f1a650d092-de_DE-1'
        ];

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('mget')->once()->with([
            'index' => 'index',
            'type' => 'type',
            'body' => [
                'ids' => $identifiers
            ]
        ])->andReturn($test_data['raw_result']);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $projections = $this->createProjections($test_data['raw_result']['docs']);
        $finder_result = new FinderResult($projections, 2);

        $this->assertEquals($finder_result, $projection_finder->getByIdentifiers($identifiers));
    }

    public function testGetByIdentifiersPartial()
    {
        $test_data = include(__DIR__ . '/Fixture/projection_finder_test_03.php');
        $identifiers = [
            'honeybee-cmf.projection_fixtures.book-a726301d-dbae-4fb6-91e9-a19188a17e71-de_DE-1',
            'honeybee-cmf.projection_fixtures.book-61d8da68-0d56-4b8b-b393-21f1a650d092-de_DE-1'
        ];

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('mget')->once()->with([
            'index' => 'index',
            'type' => 'type',
            'key' => 'value',
            'body' => [
                'ids' => $identifiers
            ]
        ])->andReturn($test_data['raw_result']);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([
                'index' => 'index',
                'type' => 'type',
                'parameters' => [ 'mget' => ['key' => 'value' ] ]
            ]),
            new NullLogger,
            $this->projection_type
        );

        $projections = $this->createProjections([ $test_data['raw_result']['docs'][0] ]);
        $finder_result = new FinderResult($projections, 1);

        $this->assertEquals($finder_result, $projection_finder->getByIdentifiers($identifiers));
    }

    public function testFind()
    {
        $test_data = include(__DIR__ . '/Fixture/projection_finder_test_04.php');
        $query = [ 'from' => 0, 'size' => 10, 'body' => [ 'query' => [ 'match_all' => [] ] ] ];

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('search')
            ->once()
            ->with(array_merge($query, [ 'index' => 'index', 'type' => 'type' ]))
            ->andReturn($test_data['raw_result']);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $projections = $this->createProjections($test_data['raw_result']['hits']['hits']);
        $finder_result = new FinderResult($projections, 2);

        $this->assertEquals($finder_result, $projection_finder->find($query));
    }

    public function testFindNoResults()
    {
        $query = [ 'from' => 0, 'size' => 10, 'body' => [ 'query' => [ 'match_all' => [] ] ] ];

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('search')
            ->once()
            ->with(array_merge($query, [ 'index' => 'index', 'type' => 'type' ]))
            ->andReturn([ 'hits' => [ 'total' => 0, 'hits' => [] ] ]);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $finder_result = new FinderResult([]);

        $this->assertEquals($finder_result, $projection_finder->find($query));
    }

    public function testScrollStart()
    {
        $query = [ 'from' => 0, 'size' => 10, 'body' => [ 'query' => [ 'match_all' => [] ] ] ];

        $this->mock_connector->shouldReceive('getConfig')->once()->andReturn(new ArrayConfig([]));
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('search')
            ->once()
            ->with(array_merge(
                $query,
                [
                    'index' => 'index',
                    'type' => 'type',
                    'search_type' => 'scan',
                    'scroll' => '1m',
                    'sort' => [ '_doc' ],
                    'size' => 10
                ]
            ))
            ->andReturn([ '_scroll_id' => 'test_scroll_id', 'hits' => [ 'hits' => [] ] ]);

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $finder_result = new FinderResult([], 0, 0, 'test_scroll_id');

        $this->assertEquals($finder_result, $projection_finder->scrollStart($query));
    }

    public function testScrollNext()
    {
        $test_data = include(__DIR__ . '/Fixture/projection_finder_test_04.php');

        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('scroll')
            ->once()
            ->with([ 'scroll' => '1m', 'scroll_id' => 'test_scroll_id' ])
            ->andReturn(array_merge($test_data['raw_result'], [ '_scroll_id' => 'next_scroll_id' ]));

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $projections = $this->createProjections($test_data['raw_result']['hits']['hits']);
        $finder_result = new FinderResult($projections, 2, 0, 'next_scroll_id');

        $this->assertEquals($finder_result, $projection_finder->scrollNext('test_scroll_id'));
    }

    public function testScrollEnd()
    {
        $this->mock_connector->shouldReceive('getConnection')->once()->andReturn($this->mock_client);
        $this->mock_client->shouldReceive('clearScroll')
            ->once()
            ->with([ 'scroll_id' => 'last_scroll_id' ])
            ->andReturnNull();

        $projection_finder = new ProjectionFinder(
            $this->mock_connector,
            new ArrayConfig([ 'index' => 'index', 'type' => 'type' ]),
            new NullLogger,
            $this->projection_type
        );

        $this->assertNull($projection_finder->scrollEnd('last_scroll_id'));
    }

    protected function createProjections(array $results)
    {
        $projections = [];
        foreach ($results as $result) {
            $projections[] = $this->projection_type->createEntity($result['_source']);
        }
        return $projections;
    }
}