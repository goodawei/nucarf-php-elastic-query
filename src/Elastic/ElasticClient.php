<?php

namespace Nucarf\Elastic;

use Elasticsearch\ClientBuilder;

class ElasticClient
{
    protected static $defaultHosts = [];

    /**
     * 设置默认链接的 es 集群
     *
     * @param array $hosts
     */
    public static function setDefaultHosts(array $hosts)
    {
        self::$defaultHosts = $hosts;
    }

    /**
     * 发起查询
     *
     * @param string $index 索引名
     *
     * @return \Nucarf\Elastic\ElasticQuery
     */
    public static function query(string $index)
    {
        $query = new ElasticQuery();
        $query->setConnection(self::getConnection());
        $query->setIndex($index);

        return $query;
    }

    /**
     * 获取 es 客户端，默认连接 `setDefaultHosts` 设置的默认集群
     *
     * @param array $hosts
     *
     * @return \Elasticsearch\Client
     */
    public static function getConnection(array $hosts = [])
    {
        return ClientBuilder::fromConfig([
            'hosts' => $hosts ?: self::$defaultHosts,
        ]);
    }
}
