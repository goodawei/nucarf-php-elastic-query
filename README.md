# PHP ES Query

PHP ElasticSearch query，接口模仿 Laravel Eloquent

## 安装

### composer 引入

修改 `composer.json`

- 添加依赖

  ```json
  "require": {
      "danke/php-elastic-query": "dev-master"
  }
  ```

- 固定代码库路径

  ```json
  "repositories": [
        {
            "type": "composer",
            "url":  "http://packagist.danke.life"
        }
    ],
  ```

## Usage


> 本 SDK 主要面向 ES 新手，老手或者复杂查询时可以考虑自行构建 DSL。


## 使用说明

主要介绍两种使用方式：直接调用、Lego 用例，两者初始化过程是一致的：

```php
use Danke\Elastic\ElasticClient;

$query = ElasticClient::query('index_name');
```


### 直接调用

##### 筛选条件

接口基本和 Eloquent 一致，示例代码如下：

```php
$query
    ->where('eq', '=', 'eq value')
    ->where('gt', '>', 'gt value')
    ->where('gte', '>=', 'gte value')
    ->where('lt', '<', 'lt value')
    ->where('lte', '<=', 'lte value')
    ->where('like', 'like', '%like value%')
    ->where('not_like', 'not like', '%not like value%')
    ->whereEquals('eq', 'eq value')
    ->whereNotEquals('neq', 'neq value')
    ->whereContains('contains', 'contains value')
    ->whereNotContains('not_contains', 'not contains value')
    ->whereBetween('num_field', [1, 100])
    ->whereBetween('date_field', [carbon('today'), carbon('tomorrow')])
    ->whereNotBetween('not_between', [101, 200])
    ->whereNull('null')
    ->whereNotNull('not_null')
    ->whereIn('in', ['a', 'b', 'c'])
    ->whereNotIn('not_in', ['d', 'e', 'f']);
```

##### 嵌套条件 & or where

```php
$query->where(function (ElasticQuery $query) {
    $query->where('name', 'zhwei')
        ->orWhere('age', '=', 18)
        ->orWhereNotNull('hobby')
        ...
});
```


##### 去重结果（聚合）

```php
$query->distinct('field_name');
$query->search()->getDistinctValues('field_name');
```

> distinct 是 TermsAggregation 的简单封装，其他聚合查询可通过下面方式自定义 dsl
>
> ```php
> $agg = new SomeAggregation(...);
> $query->getDsl()->addAggregation($agg)
> $aggResult = $query->search()->get('aggregations');
> ```

##### 获取结果集

```php
$query->get()

// 仅返回指定字段
$query->get(['name', 'code'])
```

分页接口

```php
$query->paginate(100)
```

仅返回 id 列表

```php
$query->pluckIds()
```

> 注意：
>
> 普通查询最多支持查询 10000 条，查询超过此限额数据需要使用 scroll 相关接口
> 

##### Scroll 查询

```php
// 创建 scroll 并返回第一次查询结果
$scrollResult = $query->getScroll();
foreach ($scrollResult->items as $item) {
    // ...
}

// 后续
while ($scrollResult->items->count() > 0) {
    $scrollResult = $query->getByScrollId($scrollResult->scrollId);
    // ....
}
```

##### 搭配数据库使用

由于 ES 索引数据更新存在一定的延迟，如果对准确性要求较高的场景，或其他数据需求，可以考虑仅使用 ES 搜索出符合条件的 id 列表，然后调用服务查询实际数据：

```php
$query->retriever(function ($ids) {
    return $corpService->findCorpUsersByIds($ids);
})
```

通过 retriever 设置获取原始数据的回调函数后，`get` 和 `paginate` 查询出的数据会自动通过上面回调查询原始数据。


### Lego 用例

用例如下，注意事项：

- **注一**：`paginate` 需在 `$grid` 上调用

```php
$filter = Lego::filter($query);
$filter->addSelect('tableName')->options([....])

$grid = Lego::grid($filter);
$grid->paginate(30); // 注一

return $grid->view();
```

由于 Lego 内部调用的是 query 的 `paginate` 函数，所以 `retriever` 在 Lego 里也是可以用的。


### 高级用法

此 SDK 基于 `ongr/elasticsearch-dsl` 封装而来，通过 `$query->getDsl()` 可拿到 Search 对象，可利用 `elasticsearch-dsl` 的接口构造任意 DSL 语句。

```php
$query->getDsl();
```















