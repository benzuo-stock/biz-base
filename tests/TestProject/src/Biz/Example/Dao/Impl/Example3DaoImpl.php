<?php

namespace TestProject\Biz\Example\Dao\Impl;

use TestProject\Biz\Example\Dao\ExampleDao;
use Benzuo\Biz\Base\Dao\GeneralDaoImpl;

class Example3DaoImpl extends GeneralDaoImpl implements ExampleDao
{
    protected $table = 'example3';

    public function findByName($name, $start, $limit)
    {
        return $this->search(array('name' => $name), array('created' => 'DESC'), $start, $limit);
    }

    public function findByNameAndId($name, $id)
    {
        return $this->findByFields(array('name' => $name, 'id' => $id));
    }

    public function findByIds(array $ids, array $orderBys, $start, $limit)
    {
        $marks = str_repeat('?,', count($ids) - 1).'?';
        $sql = "SELECT * FROM {$this->table()} WHERE id IN ({$marks})";
        return $this->db()->fetchAll($this->sql($sql, $orderBys, $start, $limit), $ids) ?: array();
    }

    public function declares()
    {
        return array(
            'timestamps' => array('created_time', 'updated_time'),
            'serializes' => array('ids1' => 'json', 'ids2' => 'delimiter', 'null_value' => 'json'),
            'orderbys' => array('name', 'created_time'),
            'conditions' => array(
                'name = :name',
                'name pre_LIKE :pre_like',
                'name suF_like :suf_name',
                'name LIKE :like_name',
                'id iN (:ids)',
                'ids1 = :ids1'
            ),
        );
    }
}
