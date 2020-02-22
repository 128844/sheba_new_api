<?php namespace Sheba\Cache\Category\Tree;


use App\Sheba\Cache\Category\Tree\CategoryTreeDataStore;
use Sheba\Cache\CacheObject;
use Sheba\Cache\CacheRequest;
use Sheba\Cache\DataStoreObject;

class CategoryTreeCache implements CacheObject
{
    /** @var CategoryTreeCacheRequest */
    private $categoryTreeCacheRequest;

    public function getCacheName(): string
    {
        return sprintf("%s::%s_%d", $this->getRedisNamespace(), 'location', $this->categoryTreeCacheRequest->getLocationId());
    }

    public function getRedisNamespace(): string
    {
        return 'category_tree';
    }


    public function getExpirationTimeInSeconds(): int
    {
        return 1 * 60 * 60;
    }

    public function setCacheRequest(CacheRequest $cache_request)
    {
        $this->categoryTreeCacheRequest = $cache_request;
    }
}