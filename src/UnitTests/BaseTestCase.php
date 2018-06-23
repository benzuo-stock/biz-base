<?php

namespace Benzuo\Biz\Base\UnitTests;

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected static $biz;

    public static function setUpBeforeClass()
    {
    }

    public function setUp()
    {
        static::emptyDatabase();
    }

    public static function setBiz($biz)
    {
        static::$biz = $biz;
    }

    public static function emptyDatabaseQuickly()
    {
        $clear = new DatabaseDataClearer(static::$biz['db']);
        $clear->clearQuickly();
    }

    public static function emptyDatabase()
    {
        $clear = new DatabaseDataClearer(static::$biz['db']);
        $clear->clear();
    }
}
