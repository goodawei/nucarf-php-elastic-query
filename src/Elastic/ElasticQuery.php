<?php

namespace Nucarf\Elastic;

use Nucarf\Elastic\TraceLog\TraceLog;
use Elasticsearch\Client;
use Illuminate\Support\Collection;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\PrefixQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class ElasticQuery
{
    use Concerns\WhereNot;
    use Concerns\WhereOr;
    use Concerns\DatetimeRangeHelper;
    use Concerns\Aggregations;

    const DEFAULT_SIZE = 100;

    const DEFAULT_SCROLL_SIZE = 1000;
    const DEFAULT_SCROLL = '5m';

    const MUST = BoolQuery::MUST;
    const MUST_NOT = BoolQuery::MUST_NOT;
    const SHOULD = BoolQuery::SHOULD;
    const FILTER = BoolQuery::FILTER;

    /**
     * @var \ONGR\ElasticsearchDSL\Search
     */
    protected $dsl;

    /**
     * @var Client
     */
    protected $client;

    protected $params = [
        'index' => '',
        'type' => null,
        'body' => [],
    ];

    /**
     * 翻页接口数据量限额, 即：index.max_result_window
     * @var int
     */
    protected $maxResultWindow = 10000;

    public function __construct()
    {
        $this->dsl = new Search();
        $this->dsl->setSize(self::DEFAULT_SIZE);
    }

    public function setConnection(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 设置索引名称
     *
     * @param string $index
     */
    public function setIndex(string $index)
    {
        $this->params['index'] = $index;
    }

    /**
     * 设置 type ，记 mapping 名
     *
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->params['type'] = $type;
    }

    /**
     * index max_result_window
     *
     * @param int $max
     */
    public function setMaxResultWindow(int $max)
    {
        $this->maxResultWindow = $max;
    }

    /**
     * @return int
     */
    public function getMaxResultWindow(): int
    {
        return $this->maxResultWindow;
    }

    public function select(array $fields)
    {
        $this->dsl->setStoredFields($fields);
        return $this;
    }

    public function where($field, $operator = null, $value = null, $type = self::MUST)
    {
        if (is_null($value)) {
            if (is_null($operator)) {
                return $this->whereSubQuery($field, $type);
            }

            return $this->whereEquals($field, $operator, $type);
        }

        switch ($operator) {
            case '=':
                return $this->whereEquals($field, $value, $type);

            case '!=':
            case '<>':
                return $this->whereNotEquals($field, $value);

            case '>':
            case '<':
            case '>=':
            case '<=':
                return $this->whereRange($field, $operator, $value, $type);

            case 'in':
                return $this->whereIn($field, $value, $type);

            case 'not in':
                return $this->whereNotIn($field, $value);

            case 'like':
                $value = trim($value, '%');
                return $this->whereContains($field, $value, $type);

            case 'not like':
                $value = trim($value, '%');
                return $this->whereNotContains($field, $value);

            default:
                throw new ElasticException('unsupported operator: ' . $operator);
        }
    }

    public function whereSubQuery(callable $callable, $type = self::MUST)
    {
        $query = new static;
        call_user_func_array($callable, [$query]);
        $bool = $query->getDsl()->getQueries();
        $this->dsl->addQuery($bool, $type);
        return $this;
    }

    public function whereEquals($field, $value, $type = self::MUST)
    {
        $query = new TermQuery($field, $value);
        $this->addBoolQuery($type)->add($query);

        return $this;
    }

    public function whereNotEquals($field, $value)
    {
        return $this->whereEquals($field, $value, self::MUST_NOT);
    }

    public function whereIn($field, array $values, $type = self::MUST)
    {
        $query = new TermsQuery($field, array_values($values));
        $this->addBoolQuery($type)->add($query);
        return $this;
    }

    public function whereContains($field, $keyword, $type = self::MUST)
    {
        $keyword = trim($keyword, '*');
        $value = "*{$keyword}*";

        return $this->whereWildcard($field, $value, $type);
    }

    public function whereWildcard($field, $value, $type = self::MUST)
    {
        $value = str_limit($value, 50);
        $query = new WildcardQuery($field, $value);
        $this->addBoolQuery($type)->add($query);
        return $this;
    }

    public function whereNotContains($field, $keyword)
    {
        return $this->whereContains($field, $keyword, self::MUST_NOT);
    }

    public function whereStartsWith($field, $value)
    {
        $query = new PrefixQuery($field, $value);
        $this->addBoolQuery()->add($query);
        return $this;
    }

    public function whereNotIn($field, array $values)
    {
        $query = new TermsQuery($field, array_values($values));
        $this->addBoolQuery(self::MUST_NOT)->add($query);
        return $this;
    }

    public function whereNotNull($field, $type = self::MUST)
    {
        $query = new ExistsQuery($field);
        $this->addBoolQuery($type)->add($query);
        return $this;
    }

    public function whereNull($field)
    {
        return $this->whereNotNull($field, self::MUST_NOT);
    }

    /**
     * where between
     *
     * 如果是精确的时间区间，起止时间请使用 Carbon 对象
     *
     * @param $field
     * @param array $values
     * @param string $type
     * @return $this
     */
    public function whereBetween($field, array $values, $type = self::MUST)
    {
        list($min, $max) = $values;

        // 判断范围值是否是日期，是否需要添加时区参数
        $timezone = false;
        if (self::isEmptyString($min)) {
            $min = null;
        } else {
            list($min, $timezone1) = self::tryFormatDatetimeForRange($min);
            $timezone = $timezone || $timezone1;
        }
        if (self::isEmptyString($max)) {
            $max = null;
        } else {
            list($max, $timezone2) = self::tryFormatDatetimeForRange($max);
            $timezone = $timezone || $timezone2;
        }

        $range = [
            RangeQuery::GTE => $min,
            RangeQuery::LTE => $max,
        ];

        if ($timezone) {
            $range['time_zone'] = date_default_timezone_get();
        }

        $rangeQuery = new RangeQuery($field, $range);
        $this->addBoolQuery($type)->add($rangeQuery);

        return $this;
    }

    protected static function isEmptyString($string)
    {
        return strlen(trim($string)) === 0;
    }

    public function whereNotBetween($field, array $values)
    {
        return $this->whereBetween($field, $values, self::MUST_NOT);
    }

    public function whereRange($field, $operator, $value, $type = self::MUST)
    {
        list($value, $timezone) = self::tryFormatDatetimeForRange($value);

        $mapping = [
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
        ];

        if (!isset($mapping[$operator])) {
            throw new ElasticException('unsupported range operator: ' . $operator);
        }

        $range = [$mapping[$operator] => $value];
        if ($timezone) {
            $range['time_zone'] = date_default_timezone_get();
        }

        $rangeQuery = new RangeQuery($field, $range);
        $this->addBoolQuery($type)->add($rangeQuery);

        return $this;
    }

    protected function addBoolQuery($type = self::MUST): BoolQuery
    {
        $boolQuery = new BoolQuery();
        $this->dsl->addQuery($boolQuery, $type);
        return $boolQuery;
    }

    /** 以下是 limit & order */

    public function limit(int $limit)
    {
        $this->dsl->setSize($limit);
    }

    public function orderBy($field, $order = FieldSort::ASC)
    {
        $sort = new FieldSort($field, $order);
        $this->dsl->addSort($sort);
        return $this;
    }

    public function orderByDesc($field)
    {
        return $this->orderBy($field, FieldSort::DESC);
    }

    /** 以下是发起查询的相关代码 */

    public function toArray()
    {
        return $this->dsl->toArray();
    }

    public function getDsl()
    {
        return $this->dsl;
    }

    /**
     * 最终发往 ES 的数据
     * @return array
     */
    public function getParams()
    {
        $params = $this->params;
        $params['body'] = $this->dsl->toArray();
        return $params;
    }

    /**
     * 查询，默认限制 `self::DEFAULT_SIZE` 条
     *
     * @param array $fields 仅返回指定字段，默认返回全部
     *
     * @return \Illuminate\Support\Collection
     * @throws \Nucarf\Elastic\ElasticException
     * @throws \Throwable
     */
    public function get(array $fields = [])
    {
        if ($this->retriever) {
            $ids = $this->pluckIds();
            $rows = $this->callRetrieve($ids);
        } else {
            $results = $this->getRawSearchResult($fields);
            $hits = $results['hits']['hits'] ?? [];
            $rows = $this->convertHitsToCollection($hits);
        }

        $this->callAfterSearch($rows);

        return $rows;
    }

    /**
     * @param array $fields
     * @param string $scroll
     * @param int $size
     *
     * @return \Nucarf\Elastic\ScrollResult
     * @throws \Throwable
     */
    public function getScroll(array $fields = [], $scroll = self::DEFAULT_SCROLL, $size = self::DEFAULT_SCROLL_SIZE)
    {
        $this->params['scroll'] = $scroll ?: self::DEFAULT_SCROLL;
        $this->limit($size);

        $results = $this->getRawSearchResult($fields);
        return $this->convertResultsToScrollResult($results);
    }

    public function getByScrollId($scrollId)
    {
        $results = TraceLog::call(
            [$this->client, 'scroll'],
            [['scroll_id' => $scrollId]]
        );

        return $this->convertResultsToScrollResult($results);
    }

    protected function convertResultsToScrollResult(array $results)
    {
        $sr = new ScrollResult();

        $sr->scrollId = $results['_scroll_id'] ?? null;
        $sr->items = $this->convertHitsToCollection(
            $results['hits']['hits'] ?? []
        );

        return $sr;
    }

    public function setPaginate(int $perPage, int $currentPage = 1)
    {
        $this->dsl->setSize($perPage);
        $this->dsl->setFrom(($currentPage - 1) * $perPage);
    }

    /**
     * 仅返回 id 列表
     *
     * @return array
     * @throws \Throwable
     */
    public function pluckIds()
    {
        $this->dsl->setStoredFields([]);
        $results = $this->getRawSearchResult();
        $ids = array_pluck($results['hits']['hits'] ?? [], '_id');
        return $ids;
    }

    /**
     * 查询并返回分页数据
     *
     * @param int $perPage
     * @param int $currentPage
     * @param array $fields
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @throws \Nucarf\Elastic\ElasticException
     * @throws \Throwable
     */
    public function paginate(int $perPage, int $currentPage = 1, array $fields = [])
    {
        $this->setPaginate($perPage, $currentPage);

        if ($this->retriever) {
            $this->dsl->setStoredFields([]);
            $results = $this->getRawSearchResult();
            $ids = array_pluck($results['hits']['hits'] ?? [], '_id');
            $items = $this->callRetrieve($ids);
        } else {
            $results = $this->getRawSearchResult($fields);
            $hits = $results['hits']['hits'] ?? [];
            $items = $this->convertHitsToCollection($hits);
        }

        $this->callAfterSearch($items);
        $total = isset($results['hits']['total']['value']) ? $results['hits']['total']['value']: 0;

        $paginator = new ElasticLengthAwarePaginator($items, $total, $perPage, $currentPage);

        // 避免翻页过多，超过 max_result_window 导致报错
        $lastPage = ceil(min($total, $this->maxResultWindow) / $perPage);
        $paginator->setLastPage($lastPage);

        return $paginator;
    }

    /**
     * 将 ES 查询出的 hits.hits 部分转为 Collection 对象
     *
     * @param array $hits
     *
     * @return \Illuminate\Support\Collection
     */
    protected function convertHitsToCollection(array $hits)
    {
        $collection = new Collection();

        foreach ($hits as $result) {
            // 未指定 fields
            if (isset($result['_source'])) {
                $collection->push($result['_source']);
                continue;
            }

            // 指定 fields
            $item = [];
            foreach ($result['fields'] as $fieldName => $values) {
                $item[$fieldName] = $values[0] ?? null;
            }
            $collection->push($item);
        }
        return $collection;
    }

    /**
     * 查询并返回 ES 的原始返回值
     *
     * @param array $fields
     *
     * @return array
     * @throws \Throwable
     */
    public function getRawSearchResult(array $fields = [])
    {
        if ($fields) {
            $this->dsl->setStoredFields($fields);
        }

        $params = $this->getParams();
        $result = TraceLog::call([$this->client, 'search'], [$params]);

        return $result;
    }

    public function search()
    {
        return new SearchResult(
            $this->getRawSearchResult()
        );
    }

    /** 配合其他数据源使用 */

    /**
     * @var callable
     */
    protected $retriever;

    /**
     * 设置此查询函数后，仅从 es 查询 id ，实际结果集会通过此函数查询
     *
     * @param callable $retriever
     *
     * @return \Nucarf\Elastic\ElasticQuery
     */
    public function retriever(callable $retriever)
    {
        $this->retriever = $retriever;

        return $this;
    }

    /**
     * @var callable
     */
    protected $afterSearch;

    public function afterSearch(callable $callback)
    {
        $this->afterSearch = $callback;
        return $this;
    }

    protected function callAfterSearch(&$data)
    {
        if ($this->afterSearch) {
            call_user_func_array($this->afterSearch, [&$data]);
        }
    }

    /**
     * @param $ids
     *
     * @return \Illuminate\Support\Collection
     * @throws \Nucarf\Elastic\ElasticException
     */
    protected function callRetrieve($ids)
    {
        $results = call_user_func_array($this->retriever, [$ids]);

        if ($results instanceof Collection) {
            return $results;
        }

        if (is_array($results)) {
            return new Collection($results);
        }

        throw new ElasticException(
            'retriever should return array or Collection instance'
        );
    }
}
