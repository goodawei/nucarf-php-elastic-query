<?php

namespace Nucarf\Elastic\Lego;

use Nucarf\Elastic\ElasticQuery;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Lego\Operator\Query;
use Lego\Operator\SuggestResult;

/**
 * Class LegoElasticQueryQuery
 * @package Danke\Elastic\Lego
 *
 * @property ElasticQuery $data   仅方便自动补全
 */
class LegoElasticQueryQuery extends Query
{
    /**
     * Lego 接收到原始数据 $data 时，会顺序调用已注册 Operator 子类的此函数，
     *  当前类能处理该类型数据，则返回实例化后的 Operator ;
     *  反之 false ，返回 false 时，继续尝试下一个 Operator 子类.
     *
     * @param $data
     *
     * @return static|false
     */
    public static function parse($data)
    {
        if ($data instanceof ElasticQuery) {
            return new self($data);
        }

        return false;
    }

    /**
     * 当前属性是否等于某值
     *
     * @param $attribute
     * @param null $value
     *
     * @return static
     */
    public function whereEquals($attribute, $value)
    {
        $this->data->whereEquals($attribute, $value);
        return $this;
    }

    /**
     * 当前属性值是否在 values 之内.
     *
     * @param $attribute
     * @param array $values
     *
     * @return static
     */
    public function whereIn($attribute, array $values)
    {
        $this->data->whereIn($attribute, $values);
        return $this;
    }

    /**
     * 当前属性大于某值
     *
     * @param $attribute
     * @param null $value
     * @param bool $equals 是否包含等于的情况, 默认不包含
     *
     * @return static
     */
    public function whereGt($attribute, $value, bool $equals = false)
    {
        $this->data->where($attribute, $equals ? '>=' : '>', $value);
        return $this;
    }

    /**
     * 当前属性小于某值
     *
     * @param $attribute
     * @param null $value
     * @param bool $equals 是否包含等于的情况, 默认不包含
     *
     * @return static
     */
    public function whereLt($attribute, $value, bool $equals = false)
    {
        $this->data->where($attribute, $equals ? '<=' : '<', $value);
        return $this;
    }

    /**
     * 当前属性包含特定字符串.
     *
     * @param $attribute
     * @param string|null $value
     *
     * @return static
     */
    public function whereContains($attribute, string $value)
    {
        $this->data->whereContains($attribute, $value);
        return $this;
    }

    /**
     * 当前属性以特定字符串开头.
     *
     * @param $attribute
     * @param string|null $value
     *
     * @return static
     */
    public function whereStartsWith($attribute, string $value)
    {
        $this->data->whereStartsWith($attribute, $value);
        return $this;
    }

    /**
     * 当前属性以特定字符串结尾.
     *
     * @param $attribute
     * @param string|null $value
     *
     * @return static
     */
    public function whereEndsWith($attribute, string $value)
    {
        $this->data->whereWildcard($attribute, '*' . $value);
        return $this;
    }

    /**
     * between, 两端开区间.
     *
     * @param $attribute
     * @param null $min
     * @param null $max
     *
     * @return static
     */
    public function whereBetween($attribute, $min, $max)
    {
        $this->data->whereBetween($attribute, [$min, $max]);
        return $this;
    }

    /**
     * Query Scope.
     */
    public function whereScope($scope, $value)
    {
        return $this;
    }

    /**
     * 特定字段的 自动补全、推荐 结果.
     *
     * @param $attribute
     * @param string $keyword
     * @param string $valueColumn default null，默认返回主键
     * @param int $limit
     *
     * @return SuggestResult
     */
    public function suggest(
        $attribute,
        string $keyword,
        string $valueColumn = null,
        int $limit = 20
    ): SuggestResult {
        return new SuggestResult([]);
    }

    /**
     * 限制条数.
     *
     * @param $limit
     *
     * @return static
     */
    public function limit($limit)
    {
        $this->data->limit($limit);
        return $this;
    }

    /**
     * order by.
     *
     * @param $attribute
     * @param bool $desc 默认升序(false), 如需降序, 传入 true
     *
     * @return static
     */
    public function orderBy($attribute, bool $desc = false)
    {
        $this->data->orderBy($attribute, $desc ? 'desc' : 'asc');
        return $this;
    }

    protected function createLengthAwarePaginator($perPage, $columns, $pageName, $page)
    {
        $columns = $this->formatColumns($columns);
        return $this->data->paginate($perPage, $page, $columns);
    }

    /**
     * 不需要查询总数的分页器.
     *
     * @param int $perPage     每页条数
     * @param array $columns   需要的字段列表
     * @param string $pageName 分页的 GET 参数名称
     * @param int $page        当前页码
     *
     * @return \Illuminate\Pagination\Paginator
     */
    protected function createLengthNotAwarePaginator($perPage, $columns, $pageName, $page)
    {
        $columns = $this->formatColumns($columns);
        $lp = $this->data->paginate($perPage, $page, $columns);
        $paginator = new Paginator($lp->items(), $perPage);
        return $paginator;
    }

    /**
     * Select from source.
     *
     * @param array $columns
     *
     * @return Collection
     */
    protected function select(array $columns)
    {
        return $this->data->get(
            $this->formatColumns($columns)
        );
    }

    protected function formatColumns($columns)
    {
        return $columns === ['*'] ? [] : $columns;
    }
}
