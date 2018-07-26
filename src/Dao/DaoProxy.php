<?php

namespace Benzuo\Biz\Base\Dao;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

class DaoProxy
{
    protected $dao;
    protected $serializer;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cacheItemPool;
    protected $cacheTables;

    public function __construct(DaoInterface $dao, SerializerInterface $serializer)
    {
        $this->dao = $dao;
        $this->serializer = $serializer;
    }

    public function initCacheAdapter(CacheItemPoolInterface $cacheItemPool, array $cacheTables)
    {
        $this->cacheItemPool = $cacheItemPool;
        $this->cacheTables = $cacheTables;
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
        // 使用方法：$this->getUserDao()->get($id, array('lock' => $lock));
        $lastArgument = end($arguments);
        reset($arguments);
        if (is_array($lastArgument) && isset($lastArgument['lock']) && true === $lastArgument['lock']) {
            $row = $this->callRealDao($method, $arguments);
            $this->unserialize($row);
            return $row;
        }

        $cacheItem = $this->getCacheItem($this->getRowCacheKey($this->getRowId($method, $arguments)));
        if ($cacheItem && $cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $row = $this->callRealDao($method, $arguments);
        $this->unserialize($row);

        $rowId = $row['id'];
        $this->setRowId($method, $arguments, $rowId);
        $cacheItem = $this->getCacheItem($this->getRowCacheKey($rowId));
        if ($cacheItem) {
            $this->setCacheItem($cacheItem->set($row));
        }

        return $row;
    }

    protected function find($method, $arguments)
    {
        return $this->search($method, $arguments);
    }

    protected function search($method, $arguments)
    {
        $cacheItem = $this->getCacheItem($this->getTableCacheKey($method, $arguments));
        if ($cacheItem && $cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $rows = $this->callRealDao($method, $arguments);
        if (!empty($rows)) {
            $this->unserializes($rows);
        }

        // 5000条以上结果不缓存
        if ($cacheItem && count($rows) <= 5000) {
            $this->setCacheItem($cacheItem->set($rows));
        }

        return $rows;
    }

    protected function count($method, $arguments)
    {
        $cacheItem = $this->getCacheItem($this->getTableCacheKey($method, $arguments));
        if ($cacheItem && $cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $count = $this->callRealDao($method, $arguments);

        if ($cacheItem) {
            $this->setCacheItem($cacheItem->set($count));
        }

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

        $this->updateCacheVersion();

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
        if (is_array($row)) {
            $this->unserialize($row);
        }

        $this->deleteCacheItem($this->getRowCacheKey($row['id']));
        $this->updateCacheVersion();

        return $row;
    }

    protected function wave($method, $arguments)
    {
        $row = $this->callRealDao($method, $arguments);
        if (is_array($row)) {
            $this->unserialize($row);
        }

        $this->deleteCacheItem($this->getRowCacheKey($row['id']));
        $this->updateCacheVersion();

        return $row;
    }

    protected function delete($method, $arguments)
    {
        $id = $this->callRealDao($method, $arguments);

        $this->deleteCacheItem($this->getRowCacheKey($id));
        $this->updateCacheVersion();

        return $id;
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

        $this->updateCacheVersion();

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

        foreach ($arguments[0] as $id) {
            $this->deleteCacheItem($this->getRowCacheKey($id));
        }
        $this->updateCacheVersion();

        return $result;
    }

    protected function batchDelete($method, $arguments)
    {
        $result = $this->callRealDao($method, $arguments);

        $this->updateCacheVersion();

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

    private function cacheEnabled()
    {
        if (!$this->cacheItemPool) {
            return false;
        }

        if (empty($this->cacheTables)) {
            return false;
        }

        if (!in_array($this->dao->table(), $this->cacheTables)) {
            return false;
        }

        return true;
    }

    /**
     * Confirms if the cache contains specified cache item.
     * @param $key
     * @return bool
     */
    private function hasCacheItem($key)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        try {
            return $this->cacheItemPool->hasItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Removes the item from the pool.
     * @param $key
     * @return bool
     */
    private function deleteCacheItem($key)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        try {
            return $this->cacheItemPool->deleteItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Return cache item from pool, this will return a new cache item if not exist
     * @param $key
     * @return bool|CacheItemInterface
     */
    private function getCacheItem($key)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        if (empty($key)) {
            return false;
        }

        try {
            return $this->cacheItemPool->getItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param CacheItemInterface $cacheItem
     * @return bool
     */
    private function setCacheItem(CacheItemInterface $cacheItem)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        return $this->cacheItemPool->save($cacheItem);
    }

    private function getTableCacheKey($method, $arguments)
    {
        return sprintf('dao.%s.v%s.%s.%s', $this->dao->table(), $this->getCacheVersion(), $method, md5(json_encode($arguments)));
    }

    private function getRowCacheKey($rowId)
    {
        if (empty($rowId)) {
            return null;
        }

        return sprintf('dao.%s.id%s', $this->dao->table(), $rowId);
    }

    private function getCacheVersion()
    {
        if (!$this->cacheEnabled()) {
            return '';
        }

        $versionKey = sprintf('dao.version.%s', $this->dao->table());
        $versionItem = $this->getCacheItem($versionKey);
        if ($versionItem->isHit()) {
            return $versionItem->get();
        }

        return $this->updateCacheVersion();
    }

    private function updateCacheVersion()
    {
        if (!$this->cacheEnabled()) {
            return '';
        }

        $versionKey = sprintf('dao.version.%s', $this->dao->table());
        $versionItem = $this->getCacheItem($versionKey);
        $versionItem->set(time());

        $this->setCacheItem($versionItem);

        return $versionItem->get();
    }

    private function getRowId($method, $arguments)
    {
        $methodHash = sprintf('%s_%s', $method, md5(json_encode($arguments)));
        $idKey = sprintf('dao.%s.hash.%s.id', $this->dao->table(), $methodHash);

        if (!$this->hasCacheItem($idKey)) {
            return null;
        }

        $idItem = $this->getCacheItem($idKey);
        if (!$idItem->isHit()) {
            return null;
        }

        return $idItem->get();
    }

    private function setRowId($method, $arguments, $id)
    {
        $methodHash = sprintf('%s_%s', $method, md5(json_encode($arguments)));
        $idKey = sprintf('dao.%s.hash.%s.id', $this->dao->table(), $methodHash);
        $idItem = $this->getCacheItem($idKey);

        $idItem->set($id);
        $this->setCacheItem($idItem);
    }
}
