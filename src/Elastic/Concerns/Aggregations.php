<?php

namespace Nucarf\Elastic\Concerns;

use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;

trait Aggregations
{
    /**
     * 获取去重结果 (TermsAggregation)
     *
     * 注意：get() 返回值中无聚合查询的结果，推荐使用 search()
     *      下面方式可以快速获取所有唯一值
     *  $query->search()->getDistinctValues($field)
     *
     * @param string $field 字段名称
     * @param int $size     返回多少个
     * @return $this
     */
    public function distinct(string $field, int $size)
    {
        $terms = new TermsAggregation($field, $field);
        $terms->addParameter('size', $size);
        $this->dsl->addAggregation($terms);

        return $this;
    }
}
