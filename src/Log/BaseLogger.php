<?php declare(strict_types=1);

namespace Room11\Jeeves\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

abstract class BaseLogger implements LoggerInterface
{
    private static $psrLevelMap = [
        PsrLogLevel::DEBUG => Level::DEBUG,
        PsrLogLevel::INFO => Level::INFO,
        PsrLogLevel::NOTICE => Level::NOTICE,
        PsrLogLevel::WARNING => Level::WARNING,
        PsrLogLevel::ERROR => Level::ERROR,
        PsrLogLevel::CRITICAL => Level::CRITICAL,
        PsrLogLevel::ALERT => Level::ALERT,
        PsrLogLevel::EMERGENCY => Level::EMERGENCY,
    ];

    protected $logLevel;

    public function __construct(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    protected function meetsLogLevel($messageLogLevel): bool
    {
        $level = isset(self::$psrLevelMap[$messageLogLevel])
            ? self::$psrLevelMap[$messageLogLevel]
            : (int)$messageLogLevel;

        return (bool)($this->logLevel & $level);
    }

    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = array())
    {
        $this->log(Level::EMERGENCY, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = array())
    {
        $this->log(Level::ALERT, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = array())
    {
        $this->log(Level::CRITICAL, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = array())
    {
        $this->log(Level::ERROR, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = array())
    {
        $this->log(Level::WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = array())
    {
        $this->log(Level::NOTICE, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = array())
    {
        $this->log(Level::INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = array())
    {
        $this->log(Level::DEBUG, $message, $context);
    }
}
