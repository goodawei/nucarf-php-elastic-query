<?php

namespace Nucarf\Elastic;

class ScrollResult
{
    /**
     * @var string
     */
    public $scrollId;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $items;
}
