<?php

namespace Tests;

use Benzuo\Biz\Base\Context\Biz;
use Benzuo\Biz\Base\Provider\DoctrineServiceProvider;
use PHPUnit\Framework\TestCase;

class AdvancedDaoImplTest extends TestCase
{
    public function __construct()
    {
        parent::__construct(null, [], '');
        $config = [
            'db.options' => [
                'driver' => getenv('DB_DRIVER'),
                'dbname' => getenv('DB_NAME'),
                'host' => getenv('DB_HOST'),
                'user' => getenv('DB_USER'),
                'password' => getenv('DB_PASSWORD'),
                'charset' => getenv('DB_CHARSET'),
                'port' => getenv('DB_PORT'),
            ],
        ];
        $biz = new Biz($config);
        $biz['autoload.aliases']['TestProject'] = 'TestProject\Biz';
        $biz->register(new DoctrineServiceProvider());
        $biz->register(new \TestProject\Biz\CacheServiceProvider());
        $biz->boot();

        $this->biz = $biz;
    }

    public function setUp()
    {
        $this->biz['db']->exec('DROP TABLE IF EXISTS `example`');
        $this->biz['db']->exec("
            CREATE TABLE `example` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(32) NOT NULL,
              `code` varchar(32) NOT NULL DEFAULT '',
              `counter1` int(10) unsigned NOT NULL DEFAULT 0,
              `counter2` int(10) unsigned NOT NULL DEFAULT 0,
              `ids1` varchar(32) NOT NULL DEFAULT '',
              `ids2` varchar(32) NOT NULL DEFAULT '',
              `null_value` VARCHAR(32) DEFAULT NULL,
              `content` text,
              `php_serialize_value` text,
              `json_serialize_value` text,
              `delimiter_serialize_value` text,
              `created_time` int(10) unsigned NOT NULL DEFAULT 0,
              `updated_time` int(10) unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    }

    public function testBatchCreate()
    {
        $mockRows = $this->mockRows();
        $this->getExampleAdvancedDao()->batchCreate($mockRows);
        $rows = $this->getExampleAdvancedDao()->search([], ['name' => 'ASC'], 0, 100);

        $this->assertEquals(count($mockRows), count($rows));
        $this->assertEquals($mockRows[0]['name'], $rows[0]['name']);
        $this->assertEquals($mockRows[4]['name'], $rows[4]['name']);
    }

    public function testBatchUpdate()
    {
        $this->clearCache();

        $mockRows = $this->mockRows();
        $this->getExampleAdvancedDao()->batchCreate($mockRows);
        $rows = $this->getExampleAdvancedDao()->search([], ['name' => 'ASC'], 0, 100);

        $this->assertEquals($mockRows[0]['name'], $rows[0]['name']);

        $this->assertNull($this->getRowCacheValue(1));
        $version1 = $this->getCacheVersion();
        $this->assertEquals(14, \strlen($version1));
        $this->assertEquals($rows, $this->getTableCacheValue($version1, 'search', [[], ['name' => 'ASC'], 0, 100]));

        $row1 = $this->getExampleAdvancedDao()->get(1);
        $this->assertEquals($row1, $this->getRowCacheValue(1));
        $row2 = $this->getExampleAdvancedDao()->get(2);
        $this->assertEquals($row2, $this->getRowCacheValue(2));
        $row3 = $this->getExampleAdvancedDao()->get(3);
        $this->assertEquals($row3, $this->getRowCacheValue(3));

        $this->getExampleAdvancedDao()->batchUpdate([2,3], [
            ['name'=>'update2'],
            ['name'=>'update3']
        ]);
        //row1 cache should keep the same
        $this->assertEquals($row1, $this->getRowCacheValue(1));
        //row2 cache should be deleted
        $this->assertNull($this->getRowCacheValue(2));
        //row3 cache should be deleted
        $this->assertNull($this->getRowCacheValue(3));
        //version1 cache should hold the old name
        $this->assertEquals('test2', $this->getTableCacheValue($version1, 'search', [[], ['name' => 'ASC'], 0, 100])[1]['name']);
        //cache version should updated
        $version2 = $this->getCacheVersion();
        $this->assertGreaterThan($version1, $version2);
        //new version cache should be null so far
        $this->assertNull($this->getTableCacheValue($version2, 'search', [[], ['name' => 'ASC'], 0, 100]));

        $updatedRows = $this->getExampleAdvancedDao()->search([], ['name' => 'ASC'], 0, 100);
        $this->assertEquals(count($mockRows), count($updatedRows));
        //new version cache should hold the updated rows
        $this->assertEquals($updatedRows, $this->getTableCacheValue($version2, 'search', [[], ['name' => 'ASC'], 0, 100]));
    }

    public function testBatchDelete()
    {
        $this->clearCache();

        $mockRows = $this->mockRows();
        $this->getExampleAdvancedDao()->batchCreate($mockRows);
        $rows = $this->getExampleAdvancedDao()->search([], ['name' => 'ASC'], 0, 100);

        $this->assertEquals($mockRows[0]['name'], $rows[0]['name']);

        $this->assertNull($this->getRowCacheValue(1));
        $version1 = $this->getCacheVersion();
        $this->assertEquals(14, \strlen($version1));
        $this->assertEquals($rows, $this->getTableCacheValue($version1, 'search', [[], ['name' => 'ASC'], 0, 100]));

        $row1 = $this->getExampleAdvancedDao()->get(1);
        $this->assertEquals($row1, $this->getRowCacheValue(1));
        $row2 = $this->getExampleAdvancedDao()->get(2);
        $this->assertEquals($row2, $this->getRowCacheValue(2));
        $row3 = $this->getExampleAdvancedDao()->get(3);
        $this->assertEquals($row3, $this->getRowCacheValue(3));

        $this->getExampleAdvancedDao()->batchDelete([2,3]);
        //row1 cache should keep the same
        $this->assertEquals($row1, $this->getRowCacheValue(1));
        //row2 cache should be deleted
        $this->assertNull($this->getRowCacheValue(2));
        //row3 cache should be deleted
        $this->assertNull($this->getRowCacheValue(3));
        //version1 cache should hold the old name
        $this->assertEquals('test2', $this->getTableCacheValue($version1, 'search', [[], ['name' => 'ASC'], 0, 100])[1]['name']);
        //cache version should updated
        $version2 = $this->getCacheVersion();
        $this->assertGreaterThan($version1, $version2);
        //new version cache should be null so far
        $this->assertNull($this->getTableCacheValue($version2, 'search', [[], ['name' => 'ASC'], 0, 100]));

        $updatedRows = $this->getExampleAdvancedDao()->search([], ['name' => 'ASC'], 0, 100);
        $this->assertEquals(count($mockRows)-2, count($updatedRows));
        //new version cache should hold the updated rows
        $this->assertEquals($updatedRows, $this->getTableCacheValue($version2, 'search', [[], ['name' => 'ASC'], 0, 100]));
    }

    private function mockRows()
    {
        return [
            ['name' => 'test1', 'ids1' => ['11111'], 'ids2' => ['12222']],
            ['name' => 'test2', 'ids1' => ['21111'], 'ids2' => ['22222']],
            ['name' => 'test3', 'ids1' => ['31111'], 'ids2' => ['32222']],
            ['name' => 'test4', 'ids1' => ['41111'], 'ids2' => ['42222']],
            ['name' => 'test5', 'ids1' => ['51111'], 'ids2' => ['52222']],
        ];
    }

    private function getCacheVersion()
    {
        $dao = $this->getExampleAdvancedDao()->table();
        return $this->biz['dao.cache.adapter']->getItem(sprintf('dao.version.%s', $dao))->get();
    }

    private function getTableCacheValue($cacheVersion, $method, $arguments)
    {
        $dao = $this->getExampleAdvancedDao()->table();
        $key = sprintf('dao.%s.v%s.%s_%s', $dao, $cacheVersion, $method, md5(json_encode($arguments)));
        return $this->biz['dao.cache.adapter']->getItem($key)->get();
    }

    private function getRowCacheValue($rowId)
    {
        $dao = $this->getExampleAdvancedDao()->table();
        $key = sprintf('dao.%s.id%s', $dao, $rowId);
        return $this->biz['dao.cache.adapter']->getItem($key)->get();
    }

    private function clearCache()
    {
        $this->biz['dao.cache.adapter']->clear();
    }

    /**
     * @return \Benzuo\Biz\Base\Dao\AdvancedDaoInterface
     */
    private function getExampleAdvancedDao()
    {
        return $this->biz->dao('TestProject:Example:ExampleAdvancedDao');
    }
}
