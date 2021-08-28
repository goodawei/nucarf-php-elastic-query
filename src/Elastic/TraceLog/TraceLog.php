<?php

namespace Nucarf\Elastic\TraceLog;

use Monolog\Logger;

class TraceLog
{
    /**
     * @var Logger
     */
    protected static $logger;

    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
    }

    public static function call(callable $callable, array $args, string $methodName = null)
    {
        if (is_null($methodName)) {
            $methodName = self::parseCallableName($callable);
        }

        $start = microtime(true);

        try {
            $result = call_user_func_array($callable, $args);
            return $result;
        } catch (\Throwable $exception) {
            throw $exception;
        } finally {
            $cost = microtime(true) - $start;
            self::method($methodName, $args, $result ?? null, $cost, $exception ?? null);
        }
    }

    protected static function parseCallableName(callable $callable)
    {
        // "class::method"
        if (is_string($callable)) {
            return $callable;
        }

        // ['class', 'method'], [$instance, 'method']
        if (is_array($callable) && count($callable) === 2) {
            list($object, $method) = $callable;
            $className = is_string($object) ? $object : get_class($object);
            return "$className::{$method}";
        }

        throw new TraceLogException('不支持为当前 $callable 类型生成函数名，需传入 $methodName 参数');
    }

    /**
     * 函数调用日志
     *
     * @param string $method        完整函数名（包括类名）
     * @param mixed $args           参数
     * @param mixed $return         返回值
     * @param int|float $cost       函数执行时间
     * @param \Throwable $exception 函数抛出的异常
     */
    public static function method(string $method, $args, $return, $cost, \Throwable $exception = null)
    {
        if (!self::$logger) {
            return;
        }

        $limit = 1024;
        $argsString = $args ? self::convertToString($args, $limit) : null;
        $returnString = $return ? self::convertToString($return, $limit) : null;
        $exceptionString = $exception ? self::convertToString($exception->__toString(), $limit) : null;

        self::$logger->info('NO POINT', [
            'method' => $method,
            'cost' => number_format($cost, 3),
            'args' => $argsString,
            'return' => $returnString,
            'exception' => $exceptionString,
        ]);
    }

    /**
     * Copy from \Illuminate\Support\Str::limit()
     *
     * @param $value
     * @param $limit
     * @param string $end
     *
     * @return string
     */
    public static function limit($value, $limit, $end = '...')
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    protected static function convertToString($input, $limit)
    {
        $string = is_string($input)
            ? $input
            : json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 限制长度
        return mb_strwidth($string, 'UTF-8') <= $limit
            ? $string
            : rtrim(mb_strimwidth($string, 0, $limit, '', 'UTF-8')) . '...';
    }
}
