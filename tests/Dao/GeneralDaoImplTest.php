<?php

namespace Tests;

use Benzuo\Biz\Base\Context\Biz;
use Benzuo\Biz\Base\Provider\DoctrineServiceProvider;
use PHPUnit\Framework\TestCase;

class GeneralDaoImplTest extends TestCase
{
    const NOT_EXIST_ID = 9999;

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

        $this->biz['db']->exec('DROP TABLE IF EXISTS `example2`');
        $this->biz['db']->exec("
            CREATE TABLE `example2` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(32) NOT NULL,
              `code` varchar(32) NOT NULL DEFAULT '',
              `counter1` int(10) unsigned NOT NULL DEFAULT 0,
              `counter2` int(10) unsigned NOT NULL DEFAULT 0,
              `ids1` varchar(32) NOT NULL DEFAULT '',
              `ids2` varchar(32) NOT NULL DEFAULT '',
              `null_value` VARCHAR(32) DEFAULT NULL,
              `content` text,
              `created_time` int(10) unsigned NOT NULL DEFAULT 0,
              `updated_time` int(10) unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $this->biz['db']->exec('DROP TABLE IF EXISTS `example3`');
        $this->biz['db']->exec("
            CREATE TABLE `example3` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(32) NOT NULL,
              `code` varchar(32) NOT NULL DEFAULT '',
              `counter1` int(10) unsigned NOT NULL DEFAULT 0,
              `counter2` int(10) unsigned NOT NULL DEFAULT 0,
              `ids1` varchar(32) NOT NULL DEFAULT '',
              `ids2` varchar(32) NOT NULL DEFAULT '',
              `null_value` VARCHAR(32) DEFAULT NULL,
              `content` text,
              `created_time` int(10) unsigned NOT NULL DEFAULT 0,
              `updated_time` int(10) unsigned NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    public function testGet()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->get($dao);
        }
    }

    private function getTestDao()
    {
        return [
            'TestProject:Example:ExampleDao',
            'TestProject:Example:Example2Dao',
            'TestProject:Example:Example3Dao',
        ];
    }

    private function get($dao)
    {
        $dao = $this->biz->dao($dao);
        $row = $dao->create(['name' => 'test1']);

        $found = $dao->get($row['id']);
        $this->assertEquals($row['id'], $found['id']);

        $found = $dao->get(self::NOT_EXIST_ID);
        $this->assertEquals(null, $found);
    }

    public function testCreate()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->create($dao);
        }
    }

    private function create($dao)
    {
        $dao = $this->biz->dao($dao);

        $fields = [
            'name' => 'test1',
            'ids1' => [1, 2, 3],
            'ids2' => [1, 2, 3],
        ];

        $before = time();

        $saved = $dao->create($fields);

        $this->assertEquals($fields['name'], $saved['name']);
        $this->assertTrue(is_array($saved['ids1']));
        $this->assertCount(3, $saved['ids1']);
        $this->assertTrue(is_array($saved['ids2']));
        $this->assertCount(3, $saved['ids2']);
        $this->assertGreaterThanOrEqual($before, $saved['created_time']);
        $this->assertGreaterThanOrEqual($before, $saved['updated_time']);
    }

    public function testUpdate()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->update($dao);
        }
    }

    private function update($dao)
    {
        $dao = $this->biz->dao($dao);

        $row = $dao->create(['name' => 'test1']);

        $fields = [
            'name' => 'test2',
            'ids1' => [1, 2],
            'ids2' => [1, 2],
        ];

        $before = time();
        $saved = $dao->update($row['id'], $fields);

        $this->assertEquals($fields['name'], $saved['name']);
        $this->assertTrue(is_array($saved['ids1']));
        $this->assertCount(2, $saved['ids1']);
        $this->assertTrue(is_array($saved['ids2']));
        $this->assertCount(2, $saved['ids2']);
        $this->assertGreaterThanOrEqual($before, $saved['updated_time']);
    }

    public function testDelete()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->delete($dao);
        }
    }

    private function delete($dao)
    {
        $dao = $this->biz->dao($dao);

        $row = $dao->create(['name' => 'test1']);

        $deleted = $dao->delete($row['id']);

        $this->assertEquals(1, $deleted);
    }

    public function testWave()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->wave($dao);
        }
    }

    public function wave($dao)
    {
        $dao = $this->biz->dao($dao);

        $row = $dao->create(['name' => 'test1']);

        $diff = ['counter1' => 1, 'counter2' => 2];
        $waved = $dao->wave($row['id'], $diff);
        $row = $dao->get($row['id']);

        $this->assertEquals(1, $waved);
        $this->assertEquals(1, $row['counter1']);
        $this->assertEquals(2, $row['counter2']);

        $diff = ['counter1' => -1, 'counter2' => -1];
        $waved = $dao->wave($row['id'], $diff);
        $row = $dao->get($row['id']);

        $this->assertEquals(1, $waved);
        $this->assertEquals(0, $row['counter1']);
        $this->assertEquals(1, $row['counter2']);
    }

    public function testLikeSearch()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->search($dao);
        }
    }

    private function search($dao)
    {
        $dao = $this->biz->dao($dao);

        $dao->create(['name' => 'pre_test1']);
        $dao->create(['name' => 'pre_test2']);
        $dao->create(['name' => 'test3_suf']);
        $dao->create(['name' => 'test4_suf']);
        $dao->create(['name' => 'test5']);

        $preNames = $dao->search(['pre_like' => 'pre_'], ['name' => 'asc'], 0, 100);
        $sufNames = $dao->search(['suf_name' => '_suf'], ['name' => 'asc'], 0, 100);
        $likeNames = $dao->search(['like_name' => 'test'], ['name' => 'asc'], 0, 100);

        $this->assertCount(2, $preNames);
        $this->assertCount(2, $sufNames);
        $this->assertCount(5, $likeNames);
        $this->assertEquals('pre_test1', $preNames[0]['name']);
        $this->assertEquals('test4_suf', $sufNames[1]['name']);
        $this->assertEquals('test5', $likeNames[4]['name']);
    }

    public function testInSearch()
    {
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $tmp1 = $dao->create(['name' => 'pre_test1']);
        $dao->create(['name' => 'pre_test2']);
        $tmp2 = $dao->create(['name' => 'test3_suf']);
        $dao->create(['name' => 'test4_suf']);

        $results = $dao->search(['ids' => [$tmp1['id'], $tmp2['id']]], ['created_time' => 'desc'], 0, 100);

        $this->assertCount(2, $results);

        $results = $dao->search(['ids' => []], ['created_time' => 'desc'], 0, 100);

        $this->assertCount(4, $results);
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testInSearchWithException()
    {
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');
        $dao->search(['ids' => 1], [], 0, 100);
    }

    public function testCount()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->daoCount($dao);
        }
    }

    private function daoCount($dao)
    {
        $dao = $this->biz->dao($dao);

        $dao->create(['name' => 'test1']);
        $dao->create(['name' => 'test2']);
        $dao->create(['name' => 'test3']);

        $count = $dao->count(['name' => 'test2']);

        $this->assertEquals(1, $count);
    }

    public function testFindInFields()
    {
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $dao->create(['name' => 'test1', 'ids1' => ['1111'], 'ids2' => ['1111']]);
        $dao->create(['name' => 'test1', 'ids1' => ['1111'], 'ids2' => ['2222']]);
        $dao->create(['name' => 'test2', 'ids1' => ['1111'], 'ids2' => ['3333']]);
        $result = $dao->findByNameAndId('test1', '["1111"]');

        $this->assertEquals(count($result), 2);
    }

    public function testTransactional()
    {
        foreach ($this->getTestDao() as $dao) {
            $this->transactional($dao);
        }
    }

    public function transactional($dao)
    {
        $dao = $this->biz->dao($dao);

        $result = $dao->db()->transactional(function () {
            return 1;
        });

        $this->assertEquals(1, $result);
    }

    public function testNullValueUnserializer()
    {
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);

        $result = $dao->get($row['id']);
        $this->assertInternalType('array', $result['null_value']);
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testOrderBysInject()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);

        $dao->findByIds([1], ['; SELECT * FROM example'], 0, 10);

        $dao->findByIds([1], ['id' => '; SELECT * FROM example']);
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testStartInject()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);

        $dao->findByIds([1], ['created_time' => 'desc'], '; SELECT * FROM example', 10);
        $dao->findByIds([1], ['created_time' => 'desc'], 0, "; UPDATE example SET name = 'inject' WHERE id = 1");
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testLimitInject()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);
        $dao->findByIds([1], ['created_time' => 'desc'], 0, "; UPDATE example SET name = 'inject' WHERE id = 1");
    }

    public function testNonInject()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);
        $result = $dao->findByIds([1], ['created_time' => 'desc'], '0', '2');

        $this->assertCount(1, $result);
        $row = $dao->create(['name' => 'test2']);
        $result = $dao->findByIds([1, 2], ['created_time' => 'desc'], '0', 1);
        $this->assertCount(1, $result);

        $result = $dao->findByIds([1, 2], ['created_time' => 'desc'], '0', 10);
        $this->assertCount(2, $result);
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testOnlySetStart()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);
        $result = $dao->findByIds([1, 2], ['created_time' => 'desc'], '0', null);
    }

    /**
     * @expectedException \Benzuo\Biz\Base\Dao\DaoException
     */
    public function testOnlySetLimit()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create(['name' => 'test1']);
        $result = $dao->findByIds([1, 2], ['created_time' => 'desc'], null, 10);
    }

    public function testSerializes()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        $row = $dao->create([
            'name' => 'test1',
            'php_serialize_value' => ['value' => 'i_am_php_serialized_value'],
            'json_serialize_value' => ['value' => 'i_am_json_serialized_value'],
            'delimiter_serialize_value' => ['i_am_delimiter_serialized_value'],
        ]);

        foreach (['php', 'json'] as $key){
            $this->assertEquals($row[$key . '_serialize_value']['value'], "i_am_{$key}_serialized_value");
        }

        $this->assertEquals($row['delimiter_serialize_value'], ['i_am_delimiter_serialized_value']);
    }

    public function testExampleDaoCache()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:ExampleDao');

        /**
         * @var \Psr\Cache\CacheItemPoolInterface $dao
         */
        $cacheAdapter = $this->biz['dao.cache.adapter'];
        $cacheAdapter->clear();

        $row1 = $dao->create([
            'name' => 'test1',
            'counter1' => 0,
        ]);
        $this->assertEquals(1, $this->getCacheVersion($dao));
        $row1 = $dao->get($row1['id']);
        $row1Cache = $this->getCacheValue($dao, 1, 'get', [$row1['id']]);
        $this->assertEquals($row1, $row1Cache);

        $row1 = $dao->update($row1['id'], [
            'name' => 'test1_1',
        ]);
        $this->assertEquals(2, $this->getCacheVersion($dao));
        $row1 = $dao->get($row1['id']);
        $row1Cache = $this->getCacheValue($dao, 2, 'get', [$row1['id']]);
        $this->assertEquals($row1, $row1Cache);
        $row1CacheV1 = $this->getCacheValue($dao, 1, 'get', [$row1['id']]);
        $this->assertEquals('test1', $row1CacheV1['name']);

        $dao->wave($row1['id'], ['counter1' => 1]);
        $this->assertEquals(3, $this->getCacheVersion($dao));
        $row1 = $dao->get($row1['id']);
        $row1Cache = $this->getCacheValue($dao, 3, 'get', [$row1['id']]);
        $this->assertEquals($row1, $row1Cache);
        $row1CacheV1 = $this->getCacheValue($dao, 2, 'get', [$row1['id']]);
        $this->assertEquals(0, $row1CacheV1['counter1']);
    }

    public function testExample2DaoCache()
    {
        /**
         * @var \TestProject\Biz\Example\Dao\ExampleDao $dao
         */
        $dao = $this->biz->dao('TestProject:Example:Example2Dao');

        /**
         * @var \Psr\Cache\CacheItemPoolInterface $dao
         */
        $cacheAdapter = $this->biz['dao.cache.adapter'];
        $cacheAdapter->clear();

        $row1 = $dao->create([
            'name' => 'test1',
            'counter1' => 0,
        ]);
        $this->assertEquals(null, $this->getCacheVersion($dao));
        $row1 = $dao->get($row1['id']);
        $row1Cache = $this->getCacheValue($dao, 1, 'get', [$row1['id']]);
        $this->assertEquals(null, $row1Cache);
    }

    private function getCacheVersion($dao)
    {
        return $this->biz['dao.cache.adapter']->getItem(sprintf('dao.version.%s', $dao->table()))->get();
    }

    private function getCacheValue($dao, $cacheVersion, $method, $arguments)
    {
        $key = sprintf('dao.%s.v%s.%s.%s', $dao->table(), $cacheVersion, $method, md5(json_encode($arguments)));
        return $this->biz['dao.cache.adapter']->getItem($key)->get();
    }
}
