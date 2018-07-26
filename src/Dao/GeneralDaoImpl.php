<?php

namespace Benzuo\Biz\Base\Dao;

use Benzuo\Biz\Base\Context\Biz;

abstract class GeneralDaoImpl implements GeneralDaoInterface
{
    protected $biz;

    protected $table = null;

    public function __construct(Biz $biz)
    {
        $this->biz = $biz;
    }

    public function create(array $fields)
    {
        $affected = $this->db()->insert($this->table(), $fields);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert error.');
        }

        return $this->get($this->db()->lastInsertId());
    }

    public function update($id, array $fields)
    {
        $this->db()->update($this->table, $fields, array('id' => $id));

        return $this->get($id);
    }

    public function delete($id)
    {
        $this->db()->delete($this->table(), array('id' => $id));

        return $id;
    }

    public function wave($id, array $diffs)
    {
        $sets = array_map(function ($name) {
            return "{$name} = {$name} + ?";
        }, array_keys($diffs));

        $sql = "UPDATE {$this->table()} SET ".implode(', ', $sets)." WHERE id = ?";

        $this->db()->executeUpdate($sql, array_merge(array_values($diffs), array($id)));

        return $this->get($id);
    }

    public function get($id, array $options = array())
    {
        $lock = isset($options['lock']) && true === $options['lock'];
        $sql = "SELECT * FROM {$this->table()} WHERE id = ?".($lock ? ' FOR UPDATE' : '');

        return $this->db()->fetchAssoc($sql, array($id)) ?: null;
    }

    public function search($conditions, $orderBys, $start, $limit)
    {
        $builder = $this->createQueryBuilder($conditions)
            ->select('*')
            ->setFirstResult($start)
            ->setMaxResults($limit);

        $declares = $this->declares();
        foreach ($orderBys ?: array() as $order => $sort) {
            $this->checkOrderBy($order, $sort, $declares['orderbys']);
            $builder->addOrderBy($order, $sort);
        }

        return $builder->execute()->fetchAll();
    }

    public function count($conditions)
    {
        $builder = $this->createQueryBuilder($conditions)
            ->select('COUNT(*)');

        return (int) ($builder->execute()->fetchColumn(0));
    }

    /**
     * @param string $sql
     * @param array  $orderBys
     * @param int    $start
     * @param int    $limit
     *
     * @throws DaoException
     *
     * @return string
     */
    protected function sql($sql, array $orderBys = array(), $start = null, $limit = null)
    {
        if (!empty($orderBys)) {
            $sql .= ' ORDER BY ';
            $orderByStr = $separate = '';
            $declares = $this->declares();
            foreach ($orderBys as $order => $sort) {
                $this->checkOrderBy($order, $sort, $declares['orderbys']);
                $orderByStr .= sprintf('%s %s %s', $separate, $order, $sort);
                $separate = ',';
            }

            $sql .= $orderByStr;
        }

        if (null !== $start && !is_numeric($start)) {
            throw $this->createDaoException('SQL Limit must can be cast to integer');
        }

        if (null !== $limit && !is_numeric($limit)) {
            throw $this->createDaoException('SQL Limit must can be cast to integer');
        }

        $onlySetStart = $start !== null && $limit === null;
        $onlySetLimit = $limit !== null && $start === null;

        if ($onlySetStart || $onlySetLimit) {
            throw $this->createDaoException('start and limit need to be assigned');
        }

        if (is_numeric($start) && is_numeric($limit)) {
            $sql .= sprintf(' LIMIT %d, %d', $start, $limit);
        }

        return $sql;
    }

    public function table()
    {
        return $this->table;
    }

    /**
     * @return Connection
     */
    public function db()
    {
        return $this->biz['db'];
    }

    protected function getByFields($fields)
    {
        $placeholders = array_map(function ($name) {
            return "{$name} = ?";
        }, array_keys($fields));

        $sql = "SELECT * FROM {$this->table()} WHERE ".implode(' AND ', $placeholders). ' LIMIT 1 ';

        return $this->db()->fetchAssoc($sql, array_values($fields)) ?: null;
    }

    protected function findInField($field, $values)
    {
        if (empty($values)) {
            return array();
        }

        $marks = str_repeat('?,', count($values) - 1).'?';
        $sql = "SELECT * FROM {$this->table} WHERE {$field} IN ({$marks});";

        return $this->db()->fetchAll($sql, $values);
    }

    protected function findByFields($fields)
    {
        $placeholders = array_map(function ($name) {
            return "{$name} = ?";
        }, array_keys($fields));

        $sql = "SELECT * FROM {$this->table()} WHERE ".implode(' AND ', $placeholders);

        return $this->db()->fetchAll($sql, array_values($fields));
    }

    protected function createQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions, function ($value) {
            if ($value === '' || $value === null) {
                return false;
            }

            if (is_array($value) && empty($value)) {
                return false;
            }

            return true;
        });

        $builder = $this->getQueryBuilder($conditions);
        $builder->from($this->table());

        $declares = $this->declares();
        $declares['conditions'] = isset($declares['conditions']) ? $declares['conditions'] : array();

        foreach ($declares['conditions'] as $condition) {
            $builder->andWhere($condition);
        }

        return $builder;
    }

    protected function getQueryBuilder($conditions)
    {
        return new DynamicQueryBuilder($this->db(), $conditions);
    }

    private function createDaoException($message = '', $code = 0)
    {
        return new DaoException($message, $code);
    }

    private function checkOrderBy($order, $sort, $allowOrderBys)
    {
        if (!in_array($order, $allowOrderBys, true)) {
            throw $this->createDaoException(sprintf("SQL order by field is only allowed '%s', but you give `{$order}`.", implode(',', $allowOrderBys)));
        }
        if (!in_array(strtoupper($sort), array('ASC', 'DESC'), true)) {
            throw $this->createDaoException("SQL order by direction is only allowed `ASC`, `DESC`, but you give `{$sort}`.");
        }
    }
}
