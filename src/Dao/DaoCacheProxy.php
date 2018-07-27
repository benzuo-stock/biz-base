<?php

namespace Benzuo\Biz\Base\Dao;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

class DaoCacheProxy
{
    private $dao;
    private $cacheItemPool;
    private $cacheTables;

    public function __construct(DaoInterface $dao, CacheItemPoolInterface $cacheItemPool = null, array $cacheTables = [])
    {
        $this->dao = $dao;
        $this->cacheItemPool = $cacheItemPool;
        $this->cacheTables = $cacheTables;
    }

    public function getRowCache($method, $arguments)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        $rowId = $this->getRowId($method, $arguments);
        $key = $this->getRowCacheKey($rowId);
        if (empty($key) || !$this->hasCacheItem($key)) {
            return false;
        }

        $cacheItem = $this->getCacheItem($key);
        if (!$cacheItem || !$cacheItem->isHit()) {
            return false;
        }

        return $cacheItem->get();
    }

    public function setRowCache($method, $arguments, $rowId, $row)
    {
        if (!$this->cacheEnabled()) {
            return;
        }

        if (empty($rowId)) {
            return;
        }

        if (empty($this->getRowId($method, $arguments))) {
            $this->setRowId($method, $arguments, $rowId);
        }

        $cacheItem = $this->getCacheItem($this->getRowCacheKey($rowId));
        if ($cacheItem) {
            $this->setCacheItem($cacheItem->set($row));
        }
    }

    public function deleteRowCache($rowId)
    {
        if (!$this->cacheEnabled()) {
            return;
        }

        $key = $this->getRowCacheKey($rowId);
        if (empty($key)) {
            return;
        }

        $this->deleteCacheItem($key);
    }

    public function getTableCache($method, $arguments)
    {
        if (!$this->cacheEnabled()) {
            return false;
        }

        $key = $this->getTableCacheKey($method, $arguments);

        if (!$this->hasCacheItem($key)) {
            return false;
        }

        $cacheItem = $this->getCacheItem($key);
        if (!$cacheItem || !$cacheItem->isHit()) {
            return false;
        }

        return $cacheItem->get();
    }

    public function setTableCache($method, $arguments, $rows)
    {
        if (!$this->cacheEnabled()) {
            return;
        }

        $cacheItem = $this->getCacheItem($this->getTableCacheKey($method, $arguments));
        if ($cacheItem) {
            $this->setCacheItem($cacheItem->set($rows));
        }
    }

    public function deleteTableCache()
    {
        if (!$this->cacheEnabled()) {
            return;
        }

        $this->updateTableVersion();
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
     * Return cache item from pool, this will return a new cache item if not exist
     * @param $key
     * @return CacheItemInterface
     */
    private function getCacheItem($key)
    {
        if (!$this->cacheEnabled()) {
            return null;
        }

        if (empty($key)) {
            return null;
        }

        try {
            return $this->cacheItemPool->getItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            return null;
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

    private function getTableCacheKey($method, $arguments)
    {
        return sprintf('dao.%s.v%s.%s_%s', $this->dao->table(), $this->getTableVersion(), $method, md5(json_encode($arguments)));
    }

    private function getTableVersion()
    {
        if (!$this->cacheEnabled()) {
            return '';
        }

        $versionKey = sprintf('dao.version.%s', $this->dao->table());
        $versionItem = $this->getCacheItem($versionKey);
        if ($versionItem->isHit()) {
            return $versionItem->get();
        }

        $this->updateTableVersion();

        return $this->getCacheItem($versionKey)->get();
    }

    private function updateTableVersion()
    {
        if (!$this->cacheEnabled()) {
            return;
        }

        $versionKey = sprintf('dao.version.%s', $this->dao->table());
        $versionItem = $this->getCacheItem($versionKey);

        $version = (string) floor(microtime(true) * 10000);
        $versionItem->set($version);

        $this->setCacheItem($versionItem);
    }

    private function getRowCacheKey($rowId)
    {
        if (empty($rowId)) {
            return null;
        }

        return sprintf('dao.%s.id%s', $this->dao->table(), $rowId);
    }

    private function getRowId($method, $arguments)
    {
        if (!$this->cacheEnabled()) {
            return null;
        }

        $idKey = $this->getRowIdKey($method, $arguments);
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
        if (!$this->cacheEnabled()) {
            return;
        }

        $idKey = $this->getRowIdKey($method, $arguments);
        $idItem = $this->getCacheItem($idKey);

        $idItem->set($id);
        $this->setCacheItem($idItem);
    }

    private function getRowIdKey($method, $arguments)
    {
        return sprintf('dao.%s.id.%s_%s', $this->dao->table(), $method, md5(json_encode($arguments)));
    }
}