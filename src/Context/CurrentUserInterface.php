<?php

namespace Benzuo\Biz\Base\Context;

interface CurrentUserInterface
{
    public function getUsername();

    public function getRoles();

    public function getPassword();

    public function getSalt();
}
