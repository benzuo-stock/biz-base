<?php

namespace Benzuo\Biz\Base\DataStructure;

class UniquePriorityQueue extends \SplPriorityQueue
{
    protected $values = array();

    protected $serial = PHP_INT_MAX;

    public function insert($value, $priority)
    {
        if (isset($this->values[$value])) {
            return;
        }
        parent::insert($value, array($priority, $this->serial--));
        $this->values[$value] = true;
    }
}
