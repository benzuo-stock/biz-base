<?php

namespace Benzuo\Biz\Base\Service;

use Benzuo\Biz\Base\Context\Biz;

abstract class BaseService
{
    protected $biz;

    public function __construct(Biz $biz)
    {
        $this->biz = $biz;
    }
}
