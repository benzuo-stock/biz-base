<?php

namespace Benzuo\Biz\Base\Dao;

interface SerializerInterface
{
    public function serialize($method, $value);

    public function unserialize($method, $value);
}
