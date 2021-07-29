<?php

namespace Nucarf\Elastic;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;

class SearchResult implements ArrayAccess, Arrayable, JsonSerializable
{
    /**
     * @var array
     */
    protected $raw;

    public function __construct(array $raw)
    {
        $this->raw = $raw;
    }

    public function get($key, $default = null)
    {
        return Arr::get($this->raw, $key, $default);
    }

    public function getHits()
    {
        return $this->raw['hits']['hits'];
    }

    public function getDistinctValues($field)
    {
        $results = new Collection();
        foreach ($this->raw['aggregations'][$field]['buckets'] as $bucket) {
            $results->push($bucket['key']);
        }
        return $results;
    }

    // from array interfaces

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->raw);
    }

    public function offsetGet($offset)
    {
        return $this->raw[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->raw[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->raw[$offset]);
    }

    public function toArray()
    {
        return $this->raw;
    }

    public function jsonSerialize()
    {
        return $this->raw;
    }
}
