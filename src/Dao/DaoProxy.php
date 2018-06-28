<?php

namespace Benzuo\Biz\Base\Dao;

use Psr\Cache\CacheItemPoolInterface;

class DaoProxy
{
    protected $container;
    protected $dao;
    protected $serializer;

    public function __construct($container, DaoInterface $dao)
    {
        $this->container = $container;
        $this->dao = $dao;
        $this->serializer = new FieldSerializer();
    }

    public function __call($method, $arguments)
    {
        $daoProxyMethod = $this->getDaoProxyMethod($method);

        if ($daoProxyMethod) {
            return $this->$daoProxyMethod($method, $arguments);
        } else {
            return $this->callRealDao($method, $arguments);
        }
    }

    protected function getDaoProxyMethod($method)
    {
        $prefixes = array(
            'get',
            'find',
            'search',
            'count',
            'create',
            'update',
            'wave',
            'delete',
            'batchCreate',
            'batchUpdate',
            'batchDelete'
        );
        foreach ($prefixes as $prefix) {
            if (0 === strpos($method, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    protected function get($method, $arguments)
    {
        // lock模式下，需要穿透cache进入mysql
        // $lastArgument = end($arguments);
        // reset($arguments);
        // if (is_array($lastArgument) && isset($lastArgument['lock']) && true === $lastArgument['lock']) {
        //     $row = $this->callRealDao($method, $arguments);
        //     $this->unserialize($row);
        //     return $row;
        // }

        $row = $this->callRealDao($method, $arguments);
        $this->unserialize($row);

        return $row;
    }

    protected function find($method, $arguments)
    {
        return $this->search($method, $arguments);
    }

    protected function search($method, $arguments)
    {
        $rows = $this->callRealDao($method, $arguments);
        if (!empty($rows)) {
            $this->unserializes($rows);
        }

        return $rows;
    }

    protected function count($method, $arguments)
    {
        $count = $this->callRealDao($method, $arguments);

        return $count;
    }

    protected function create($method, $arguments)
    {
        $declares = $this->dao->declares();

        if (!is_array($arguments[0])) {
            throw new DaoException('create method arguments first element must be array type');
        }

        $time = time();
        if (isset($declares['timestamps']) && is_array($declares['timestamps'])) {
            foreach ($declares['timestamps'] as $value) {
                $arguments[0][$value] = $time;
            }
        }

        $this->serialize($arguments[0]);
        $row = $this->callRealDao($method, $arguments);
        $this->unserialize($row);

        return $row;
    }

    protected function update($method, $arguments)
    {
        $declares = $this->dao->declares();

        end($arguments);
        $lastKey = key($arguments);
        reset($arguments);

        if (!is_array($arguments[$lastKey])) {
            throw new DaoException('update method arguments last element must be array type');
        }

        if (isset($declares['timestamps']) && is_array($declares['timestamps'])) {
            $index = array_search('updated_time', $declares['timestamps']);
            if (false !== $index) {
                $arguments[$lastKey][$declares['timestamps'][$index]] = time();
            }
        }

        $this->serialize($arguments[$lastKey]);

        $row = $this->callRealDao($method, $arguments);

        // if (!is_array($row) && !is_numeric($row) && !is_null($row)) {
        //     throw new DaoException('update method return value must be array type or int type');
        // }

        if (is_array($row)) {
            $this->unserialize($row);
        }

        return $row;
    }

    protected function wave($method, $arguments)
    {
        $result = $this->callRealDao($method, $arguments);

        return $result;
    }

    protected function delete($method, $arguments)
    {
        $result = $this->callRealDao($method, $arguments);

        return $result;
    }

    protected function batchCreate($method, $arguments)
    {
        $declares = $this->dao->declares();

        if (!is_array($arguments[0])) {
            throw new DaoException('batchCreate method arguments first element must be array type');
        }

        $time = time();
        $rows = $arguments[0];
        $timestamps = isset($declares['timestamps']) && is_array($declares['timestamps']) ? $declares['timestamps'] : array();
        foreach ($rows as &$row) {
            foreach ($timestamps as $value) {
                $row[$value] = $time;
            }

            $this->serialize($row);
            unset($row);
        }
        $arguments[0] = $rows;

        $result = $this->callRealDao($method, $arguments);

        return $result;
    }

    protected function batchUpdate($method, $arguments)
    {
        $declares = $this->dao->declares();

        $time = time();
        $rows = $arguments[1];
        $timestamps = isset($declares['timestamps']) && is_array($declares['timestamps']) ? $declares['timestamps'] : array();
        foreach ($rows as &$row) {
            $index = array_search('updated_time', $timestamps);
            if (false !== $index) {
                $row[$timestamps[$index]] = $time;
            }

            $this->serialize($row);
            unset($row);
        }
        $arguments[1] = $rows;

        $result = $this->callRealDao($method, $arguments);

        return $result;
    }

    protected function batchDelete($method, $arguments)
    {
        $result = $this->callRealDao($method, $arguments);

        return $result;
    }

    protected function callRealDao($method, $arguments)
    {
        return call_user_func_array(array($this->dao, $method), $arguments);
    }

    protected function unserialize(&$row)
    {
        if (empty($row)) {
            return;
        }

        $declares   = $this->dao->declares();
        $serializes = empty($declares['serializes']) ? array() : $declares['serializes'];

        foreach ($serializes as $key => $method) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $row[$key] = $this->serializer->unserialize($method, $row[$key]);
        }
    }

    protected function unserializes(array &$rows)
    {
        foreach ($rows as &$row) {
            $this->unserialize($row);
        }
    }

    protected function serialize(&$row)
    {
        $declares   = $this->dao->declares();
        $serializes = empty($declares['serializes']) ? array() : $declares['serializes'];

        foreach ($serializes as $key => $method) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $row[$key] = $this->serializer->serialize($method, $row[$key]);
        }
    }

    private function getCacheKey(DaoInterface $dao, $method, $arguments)
    {
        return sprintf('dao:%s:%s:%s', $dao->table(), $method, json_encode($arguments));
    }
}
