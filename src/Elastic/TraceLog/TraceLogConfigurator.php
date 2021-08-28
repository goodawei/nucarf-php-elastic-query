<?php

namespace Nucarf\Elastic\TraceLog;

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

class TraceLogConfigurator
{

    /**
     * single 时的日志路径
     * @var string
     */
    protected static $path;

    /**
     * @var callable
     */
    protected static $contextReader;

    const METHOD_SINGLE = 'single';
    const METHOD_SYSLOG = 'syslog';

    public static function config($applicationName, $logMethod)
    {
        $logger = new Logger('csg-log');

        $formatter = new TraceLogFormatter();
        $formatter->setApplication($applicationName);

        switch ($logMethod) {
            case self::METHOD_SINGLE:
                $handler = self::configSingleHandler();
                break;

            case self::METHOD_SYSLOG:
                $handler = self::configSyslogHandler();
                break;
        }

        if (isset($handler)) {
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);
            TraceLog::setLogger($logger);
        }
    }

    protected static function configSyslogHandler()
    {
        return new SyslogHandler('csg-log', LOG_USER, 'debug');
    }

    protected static function configSingleHandler()
    {
        $path = self::$path;
        if (!$path) {
            if (function_exists('storage_path')) {
                $path = storage_path('logs/csg.log');
            } else {
                throw new \Exception('log file path not set.');
            }
        }

        return new StreamHandler($path);
    }

    public static function setPath($path)
    {
        self::$path = $path;
    }

    /**
     * @param callable $contextReader
     */
    public static function setContextReader(callable $contextReader)
    {
        self::$contextReader = $contextReader;
    }

    public static function readContext(): array
    {
        if (self::$contextReader) {
            return call_user_func_array(self::$contextReader, []);
        }

        return [];
    }
}
