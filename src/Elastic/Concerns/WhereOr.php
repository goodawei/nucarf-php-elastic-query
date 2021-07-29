<?php

namespace Nucarf\Elastic\Concerns;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;

trait WhereOr
{
    public function orWhere($field, $operator = null, $value = null): self
    {
        return $this->where($field, $operator, $value, self::SHOULD);
    }

    public function orWhereSubQuery(callable $callable): self
    {
        return $this->whereSubQuery($callable, self::SHOULD);
    }

    public function orWhereEquals($field, $value): self
    {
        return $this->whereEquals($field, $value, self::SHOULD);
    }

    public function orWhereNotEquals($field, $value): self
    {
        /** @var BoolQuery $shouldQuery */
        $shouldQuery = $this->addBoolQuery(self::SHOULD);
        $notQuery = new BoolQuery();
        $termQuery = new TermQuery($field, $value);

        $shouldQuery->add($notQuery, self::MUST_NOT);
        $notQuery->add($termQuery);

        return $this;
    }


    public function orWhereIn($field, array $values): self
    {
        return $this->whereIn($field, $values, self::SHOULD);
    }

    public function orWhereContains($field, $keyword): self
    {
        return $this->whereContains($field, $keyword, self::SHOULD);
    }

    public function orWhereWildcard($field, $value): self
    {
        return $this->whereWildcard($field, $value, self::SHOULD);
    }

    public function orWhereNotNull($field): self
    {
        return $this->whereNotNull($field, self::SHOULD);
    }

    public function orWhereNull($field): self
    {
        // query: should not exits $field
        $notQuery = new BoolQuery();
        $notQuery->add(new ExistsQuery($field));

        /** @var BoolQuery $shouldQuery */
        $shouldQuery = $this->addBoolQuery(self::SHOULD);
        $shouldQuery->add($notQuery, self::MUST_NOT);

        return $this;
    }
}
