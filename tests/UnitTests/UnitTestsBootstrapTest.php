<?php

namespace Tests;

use Benzuo\Biz\Base\Context\Biz;
use Benzuo\Biz\Base\UnitTests\UnitTestsBootstrap;
use Benzuo\Biz\Base\Provider\DoctrineServiceProvider;
use PHPUnit\Framework\TestCase;

class UnitTestsBootstrapTest extends TestCase
{
    public function testBoot()
    {
        $config = array(
            'db.options' => array(
                'driver' => getenv('DB_DRIVER'),
                'dbname' => getenv('DB_NAME'),
                'host' => getenv('DB_HOST'),
                'user' => getenv('DB_USER'),
                'password' => getenv('DB_PASSWORD'),
                'charset' => getenv('DB_CHARSET'),
                'port' => getenv('DB_PORT'),
            ),
        );
        $biz = new Biz($config);
        $biz['migration.directories'][] = dirname(__DIR__).'/TestProject/migrations';
        $biz->register(new DoctrineServiceProvider());
        $biz->boot();

        $bootstrap = new UnitTestsBootstrap($biz);
        $bootstrap->boot();
    }
}
