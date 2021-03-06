<?php

namespace Benzuo\Biz\Base\UnitTests;

use Phpmig\Api\PhpmigApplication;
use Symfony\Component\Console\Output\NullOutput;
use Benzuo\Biz\Base\Dao\MigrationBootstrap;

class UnitTestsBootstrap
{
    protected $biz;

    public function __construct($biz)
    {
        $this->biz = $biz;
    }

    public function boot()
    {
        if (isset($this->biz['db.options'])) {
            $options = $this->biz['db.options'];
            $options['wrapperClass'] = 'Benzuo\Biz\Base\Dao\TestCaseConnection';
            $this->biz['db.options'] = $options;
        }

        BaseTestCase::setBiz($this->biz);
        BaseTestCase::emptyDatabase();

        $migration = new MigrationBootstrap($this->biz);
        $container = $migration->boot();

        $adapter = $container['phpmig.adapter'];
        if (!$adapter->hasSchema()) {
            $adapter->createSchema();
        }

        $app = new PhpmigApplication($container, new NullOutput());

        $app->up();
    }
}
