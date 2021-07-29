# CHANGELOG

## Unreleased


## 1.2

- 
- ElasticQuery 增加 distinct 方法，用于查询唯一值
- ElasticQuery 增加 select 方法，用于限制返回的字段
- ElasticQuery 增加 search 方法，返回原始 ES 响应数据

## 1.0.1

- whereIn 支持传入非 vector 数组（之前会导致 es 返回 parse_exception 异常）
