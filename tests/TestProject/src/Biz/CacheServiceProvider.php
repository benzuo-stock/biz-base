<?php

namespace TestProject\Biz;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(Container $biz)
    {
        $biz['dao.cache.adapter'] = function ($biz) {
            return new RedisAdapter(RedisAdapter::createConnection('redis://localhost'), '', 3600);
        };

        $biz['dao.cache.tables'] = [
            'example'
        ];
    }
}
