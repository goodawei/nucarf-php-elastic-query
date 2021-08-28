<?php

namespace Nucarf\Elastic\TraceLog;

use Monolog\Formatter\JsonFormatter;

class TraceLogFormatter extends JsonFormatter
{
    /**
     * @var callable
     */
    protected $contextReader;

    protected $baseContext = [];

    public function __construct(int $batchMode = self::BATCH_MODE_JSON, bool $appendNewline = true)
    {
        parent::__construct($batchMode, $appendNewline);

        $this->baseContext = [
            'php_sapi' => PHP_SAPI,
            'pid' => getmypid(),
            'application' => null,
            'serverHost' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
        ];
    }

    public function format(array $record)
    {
        $base = [
            'time' => $record['datetime']->format('Y-m-d H:i:s.v'),
            'level' => $record['level_name'],
            'traceId' => RequestId::getId(),
            'sessionId' => \Session::getId(),
            'userHost' => \Input::ip(),
            'url' => \Input::url(),
        ];

        $context = TraceLogConfigurator::readContext();
        $body = array_merge(
            $base,
            $this->baseContext,
            $context ?? [],          // 自定义的上下文信息
            $record['context'] ?? [] // 实际传入的日志内容
        );

        return $this->toJson(array_filter($body)) . ($this->appendNewline ? "\n" : '');
    }

    public function setApplication(string $application)
    {
        $this->baseContext['application'] = $application;
    }

    public function setContextReader(callable $contextReader)
    {
        $this->contextReader = $contextReader;
    }
}
