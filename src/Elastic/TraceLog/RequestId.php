<?php
/**
 * Created by PhpStorm.
 * User: lihongwei
 * Date: 2021-07-29
 * Time: 11:11
 */

namespace Nucarf\Elastic\TraceLog;

class RequestId
{
    protected static $id;

    public static function getId()
    {
        return is_null(self::$id) ? \Input::header('X-Request-ID') : self::$id;
    }

    public static function setId($id)
    {
        self::$id = $id;
    }

    public static function clear()
    {
        self::setId(null);
    }
}