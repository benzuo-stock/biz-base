<?php

namespace Benzuo\Biz\Base\Dao;

use Phpmig\Adapter;
use Pimple\Container;

class MigrationBootstrap
{
    public function __construct($biz)
    {
        $this->biz = $biz;
    }

    public function boot()
    {
        $container = new Container();
        $container['biz'] = $this->biz;
        $container['db'] = $container['biz']['db'];

        // see: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/cookbook/mysql-enums.html
        $container['db']->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $container['phpmig.adapter'] = function ($container) {
            return new Adapter\Doctrine\DBAL($container['db'], 'migrations');
        };

        $migrations = array();
        $directories = $this->biz['migration.directories'];
        foreach ($directories as $directory) {
            $migrations = array_merge($migrations, glob("{$directory}/*.php"));
        }
        $container['phpmig.migrations'] = $migrations;

        if (count($directories) > 0) {
            $container['phpmig.migrations_path'] = reset($directories);
        }

        return $container;
    }
}
