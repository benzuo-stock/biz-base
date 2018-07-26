<?php

namespace Benzuo\Biz\Base\Dao;

interface AdvancedDaoInterface extends GeneralDaoInterface
{
    public function batchDelete(array $conditions);

    public function batchCreate($rows);

    public function batchUpdate($identifies, $updateColumnsList, $identifyColumn = 'id');
}
