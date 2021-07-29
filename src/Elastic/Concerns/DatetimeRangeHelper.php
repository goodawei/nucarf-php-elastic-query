<?php namespace Nucarf\Elastic\Concerns;

use DateTime;

trait DatetimeRangeHelper
{
    /**
     * 尝试将带时间的日期转换为 ES 可识别的格式
     *
     * 1、如果 input 是 Carbon 对象：格式化为 iso8601 字符串（带时区）
     * 2、如果 input 非字符串：不做任何处理
     * 3、如果 input 是符合格式 `Y-m-d` 的日期字符串：不做任何处理，并返回 time_zone 参数
     * 4、如果 input 是符合格式 `Y-m-d H:i:s` 的时间字符串：格式化为 iso8601 字符串（带时区）
     *
     * @param $input
     *
     * @return array
     */
    protected static function tryFormatDatetimeForRange($input)
    {
        if ($input instanceof DateTime) {
            return [$input->format(DateTime::ATOM), true];
        }

        if (!is_string($input)) {
            return [$input, false];
        }

        // iso8601 格式，es 可以直接识别，并且带时区
        if (DateTime::createFromFormat(DateTime::ATOM, $input) !== false) {
            return [$input, true];
        }

        // 检查是否日期字符串: Y-m-d, 是则添加 time_zone 参数
        // es 在针对日期做 range 时会对值进行四舍五入，例如值为 2020-03-08 时:
        //     gt:  '2020-03-08T23:59:59.999'
        //     gte: '2020-03-08T00:00:00.000'
        //     lt:  '2020-03-08T00:00:00.000'
        //     lte: '2020-03-08T23:59:59.999'
        // 详细文档见: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html#range-query-date-math-rounding
        // 另外默认四舍五入的值是 UTC 时区，所以需要添加 time_zone 参数
        if ($date = DateTime::createFromFormat('Y-m-d', $input)) {
            // 上面使用 Y-m-d 解析日期时会兼容 2020-3-20 格式，但 es 只支持 2020-03-20 这种格式，
            // 所以需要重新进行 format，感觉这是个 PHP 的坑
            return [$date->format('Y-m-d'), true];
        }

        // 兼容时间的日期字符串
        if ($datetime = DateTime::createFromFormat('Y-m-d H:i:s', $input)) {
            return [$datetime->format(DateTime::ATOM), true];
        }

        return [$input, false];
    }
}
