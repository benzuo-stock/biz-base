<?php

namespace Benzuo\Biz\Base\Dao;

abstract class AdvancedDaoImpl extends GeneralDaoImpl implements AdvancedDaoInterface
{
    public function batchDelete(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        $marks = str_repeat('?,', count($ids) - 1).'?';
        $sql = "DELETE FROM {$this->table()} WHERE id IN ({$marks});";

        $this->db()->executeUpdate($sql, $ids);

        return $ids;
    }

    public function batchCreate($rows)
    {
        if (empty($rows)) {
            return;
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

            $params = [];
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
    }

    public function batchUpdate(array $ids, array $updateRows)
    {
        $updateFieldNames = array_keys(reset($updateRows));
        $this->db()->checkFieldNames($updateFieldNames);

        $count = count($ids);
        $pageSize = 500;
        $pageCount = ceil($count / $pageSize);

        for ($i = 1; $i <= $pageCount; ++$i) {
            $start = ($i - 1) * $pageSize;
            $partIds = array_slice($ids, $start, $pageSize);
            $partUpdateRows = array_slice($updateRows, $start, $pageSize);
            $this->partUpdate($partIds, $partUpdateRows, 'id', $updateFieldNames);
        }

        return $ids;
    }

    /**
     * @param $identifies, eg:[1,2]
     * @param $updateRows, eg:[['name'=>'newname1', 'code'=>'newcode1'],['name'=>'newname2', 'code'=>'newcode2']]
     * @param $identifyColumn, eg:id
     * @param $updateFieldNames, eg:[name, code]
     *
     * @return int
     */
    private function partUpdate($identifies, $updateRows, $identifyColumn, $updateFieldNames)
    {
        $sql = "UPDATE {$this->table} SET ";

        $updateSql = [];

        $params = [];
        foreach ($updateFieldNames as $updateFieldName) {
            $caseWhenSql = "{$updateFieldName} = CASE {$identifyColumn} ";

            foreach ($identifies as $identifyIndex => $identify) {
                $params[] = $updateRows[$identifyIndex][$updateFieldName];
                $caseWhenSql .= " WHEN '{$identify}' THEN ? ";
                if ($identifyIndex === count($identifies) - 1) {
                    $caseWhenSql .= " ELSE {$updateFieldName} END";
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

        $this->db()->executeUpdate($sql, $params);
    }
}
