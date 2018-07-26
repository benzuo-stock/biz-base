<?php

namespace Benzuo\Biz\Base\Dao;

abstract class AdvancedDaoImpl extends GeneralDaoImpl implements AdvancedDaoInterface
{
    public function batchDelete(array $conditions)
    {
        $declares = $this->declares();
        $declareConditions = isset($declares['conditions']) ? $declares['conditions'] : array();
        array_walk($conditions, function (&$condition, $key) use ($declareConditions) {
            $isInDeclareCondition = false;
            foreach ($declareConditions as $declareCondition) {
                if (preg_match('/:'.$key.'/', $declareCondition)) {
                    $isInDeclareCondition = true;
                }
            }

            if (!$isInDeclareCondition) {
                $condition = null;
            }
        });

        $conditions = array_filter($conditions);

        if (empty($conditions) || empty($declareConditions)) {
            return 0;
        }

        $builder = $this->createQueryBuilder($conditions)
            ->delete($this->table);

        return $builder->execute();
    }

    public function batchCreate($rows)
    {
        if (empty($rows)) {
            return array();
        }

        $rows = array_values($rows);
        $columns = array_keys(reset($rows));
        $this->db()->checkFieldNames($columns);
        $columnStr = implode(',', $columns);

        $count = count($rows);
        $pageSize = 1000;
        $pageCount = ceil($count / $pageSize);

        for ($i = 1; $i <= $pageCount; ++$i) {
            $start = ($i - 1) * $pageSize;
            $pageRows = array_slice($rows, $start, $pageSize);

            $params = array();
            $sql = "INSERT INTO {$this->table} ({$columnStr}) values ";
            foreach ($pageRows as $key => $row) {
                $marks = str_repeat('?,', count($row) - 1).'?';

                if (0 != $key) {
                    $sql .= ',';
                }
                $sql .= "({$marks})";

                $params = array_merge($params, array_values($row));
            }

            $this->db()->executeUpdate($sql, $params);
            unset($params);
        }

        return true;
    }

    public function batchUpdate($identifies, $updateColumnNewValues, $identifyColumn = 'id')
    {
        $updateColumns = array_keys(reset($updateColumnNewValues));

        $this->db()->checkFieldNames($updateColumns);
        $this->db()->checkFieldNames(array($identifyColumn));

        array_walk($identifies, 'intval');

        $count = count($identifies);
        $pageSize = 500;
        $pageCount = ceil($count / $pageSize);

        for ($i = 1; $i <= $pageCount; ++$i) {
            $start = ($i - 1) * $pageSize;
            $partIdentifies = array_slice($identifies, $start, $pageSize);
            $partUpdateColumnNewValues = array_slice($updateColumnNewValues, $start, $pageSize);
            $this->partUpdate($partIdentifies, $partUpdateColumnNewValues, $identifyColumn, $updateColumns);
        }
    }

    /**
     * @param $identifies, eg:[1,2]
     * @param $updateColumnNewValues, eg:[['name'=>'newname1', 'code'=>'newcode1'],['name'=>'newname2', 'code'=>'newcode2']]
     * @param $identifyColumn, eg:id
     * @param $updateColumns, eg:[name, code]
     *
     * @return int
     */
    private function partUpdate($identifies, $updateColumnNewValues, $identifyColumn, $updateColumns)
    {
        $sql = "UPDATE {$this->table} SET ";

        $updateSql = array();

        $params = array();
        foreach ($updateColumns as $updateColumn) {
            $caseWhenSql = "{$updateColumn} = CASE {$identifyColumn} ";

            foreach ($identifies as $identifyIndex => $identify) {
                $params[] = $updateColumnNewValues[$identifyIndex][$updateColumn];
                $caseWhenSql .= " WHEN '{$identify}' THEN ? ";
                if ($identifyIndex === count($identifies) - 1) {
                    $caseWhenSql .= " ELSE {$updateColumn} END";
                }
            }

            $updateSql[] = $caseWhenSql;
        }

        $sql .= implode(',', $updateSql);

        $identifiesStr = '';
        foreach ($identifies as $identify) {
            $identifiesStr .= "'{$identify}',";
        }
        $identifiesStr = rtrim($identifiesStr, ',');

        $sql .= " WHERE {$identifyColumn} IN ({$identifiesStr})";

        return $this->db()->executeUpdate($sql, $params);
    }
}
