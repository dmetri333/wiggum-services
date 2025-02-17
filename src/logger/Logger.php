<?php
namespace wiggum\services\logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class documentation
 */
class Logger extends AbstractLogger
{

    /**
     * options
     * @var array
     */
    protected $options = [
        'level' => LogLevel::DEBUG
    ];

    /**
     * Log Levels
     * @var array
     */
    protected $logLevels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7
    ];

    /**
     * Class constructor
     *
     * @param array  $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, string|\Stringable $message, array $context = []) : void
    {
        if ($this->logLevels[$this->options['level']] < $this->logLevels[$level]) {
            return;
        }

        $this->write($this->formatMessage($level, $message, $context));
    }

    /**
     * Formats the message for logging.
     *
     * @param  string $level   The Log Level of the message
     * @param  string $message The message to log
     * @param  array  $context The context
     * @return string
     */
    protected function formatMessage($level, $message, $context = []): string
    {
        $line = "[{$level}] {$message}";
       
        if (!empty($context)) {
            $line .= PHP_EOL.$this->indent($this->contextToString($context));
        }

        return $line;
    }

    /**
     * Writes a line to the log
     *
     * @param string $message Line to write to the log
     * @return void
     */
    public function write(string $message): void
    {
        error_log($message);
    }

    /**
     * Takes the given context and coverts it to a string.
     *
     * @param  array $context The Context
     * @return string
     */
    protected function contextToString($context): string
    {
        $export = '';
        foreach ($context as $key => $value) {
            $export .= "{$key}: ";
            $export .= preg_replace(array(
                '/=>\s+([a-zA-Z])/im',
                '/array\(\s+\)/im',
                '/^  |\G  /m'
            ), array(
                '=> $1',
                'array()',
                '    '
            ), str_replace('array (', 'array(', var_export($value, true)));
            $export .= PHP_EOL;
        }
        return str_replace(array('\\\\', '\\\''), array('\\', '\''), rtrim($export));
    }

    /**
     * Indents the given string with the given indent.
     *
     * @param  string $string The string to indent
     * @param  string $indent What to use as the indent.
     * @return string
     */
    protected function indent($string, $indent = '    '): string
    {
        return $indent.str_replace("\n", "\n".$indent, $string);
    }

}