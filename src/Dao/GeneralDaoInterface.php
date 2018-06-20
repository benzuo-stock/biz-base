<?php

namespace Benzuo\Biz\Base\Dao;

interface GeneralDaoInterface extends DaoInterface
{
    public function create($fields);

    public function update($id, array $fields);

    public function delete($id);

    public function get($id, $lock = false);

    public function search($conditions, $orderBys, $start, $limit);

    public function count($conditions);

    public function wave(array $ids, array $diffs);
}
