<?php

namespace Benzuo\Biz\Base\Context;

interface BootableProviderInterface
{
    public function boot(Biz $biz);
}
