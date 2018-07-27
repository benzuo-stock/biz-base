<?php

namespace TestProject\Biz\Example\Dao\Impl;

use Benzuo\Biz\Base\Dao\AdvancedDaoImpl;
use TestProject\Biz\Example\Dao\ExampleAdvancedDao;

class ExampleAdvancedDaoImpl extends AdvancedDaoImpl implements ExampleAdvancedDao
{
    protected $table = 'example';

    public function declares()
    {
        return array(
            'timestamps' => array('created_time', 'updated_time'),
            'serializes' => array(
                'ids1'       => 'json',
                'ids2'       => 'delimiter',
                'null_value' => 'json',
                'php_serialize_value' => 'php',
                'json_serialize_value' => 'json',
                'delimiter_serialize_value' => 'delimiter',
            ),
            'orderbys'   => array('name', 'created_time'),
            'conditions' => array(
                'name = :name',
                'name pre_LIKE :pre_like',
                'name suF_like :suf_name',
                'name LIKE :like_name',
                'id iN (:ids)',
                'ids1 = :ids1',
            ),
        );
    }
}
