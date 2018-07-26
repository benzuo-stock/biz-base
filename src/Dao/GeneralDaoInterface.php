<?php

namespace Benzuo\Biz\Base\Dao;

interface GeneralDaoInterface extends DaoInterface
{
    public function create(array $fields);

    public function update($id, array $fields);

    public function delete($id);

    public function wave($id, array $diffs);

    public function get($id, array $options = array());

    public function search($conditions, $orderBys, $start, $limit);

    public function count($conditions);
}
