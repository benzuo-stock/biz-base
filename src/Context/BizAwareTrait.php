<?php

namespace Benzuo\Biz\Base\Context;

trait BizAwareTrait
{
    /**
     * @var Biz
     */
    protected $biz;

    public function setBiz(Biz $biz)
    {
        $this->biz = $biz;
    }
}
