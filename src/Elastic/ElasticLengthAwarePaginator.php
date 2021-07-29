<?php

namespace Nucarf\Elastic;

use Illuminate\Pagination\LengthAwarePaginator;

class ElasticLengthAwarePaginator extends LengthAwarePaginator
{
    /**
     * @param int $lastPage
     */
    public function setLastPage(int $lastPage)
    {
        $this->lastPage = $lastPage;
    }
}
