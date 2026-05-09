<?php

declare(strict_types=1);

namespace FlexPHP\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 file-based Logger.
 *
 * Writes structured log lines to a file on disk. Supports minimum-level
 * filtering and automatic log rotation when the file exceeds 10 MB.
 *
 * Log line format:
 *   [2025-01-01 12:00:00] [ERROR] The message {"context":"value"}
 *
 * Rotation scheme:
 *   app.log      → app.log.1  (newest previous)
 *   app.log.1    → app.log.2
 *   ...up to MAX_ROTATIONS files are kept
 */
class Logger extends AbstractLogger
{
    /**
     * Maximum file size in bytes before rotation is triggered (10 MB).
     */
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /**
     * Maximum number of rotated log files to keep.
     */
    private const MAX_ROTATIONS = 5;

    /**
     * PSR-3 log level hierarchy (lowest to highest severity).
     * Used for minimum-level filtering.
     */
    private const LEVEL_PRIORITY = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * Absolute path to the active log file (e.g. /var/app/storage/logs/app.log).
     */
    private string $logPath;

    /**
     * Minimum log level. Messages below this severity are silently discarded.
     */
    private string $minLevel;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param string $logPath Absolute path to the log file.
     *                        Missing parent directories are created automatically.
     * @param string $level   Minimum PSR-3 level to record (default: 'debug').
     */
    public function __construct(string $logPath, string $level = LogLevel::DEBUG)
    {
        $this->logPath  = $logPath;
        $this->minLevel = $this->normaliseLevel($level);

        // Ensure the log directory exists
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // -------------------------------------------------------------------------
    // PSR-3 AbstractLogger implementation
    // -------------------------------------------------------------------------

    /**
     * Write a log entry at the given level.
     *
     * Performs minimum-level filtering, interpolates context placeholders into
     * the message, formats the log line, checks for rotation, then appends.
     *
     * @param mixed            $level   PSR-3 log level string constant.
     * @param string|\Stringable $message The log message (may contain {placeholder} tokens).
     * @param array            $context Key/value context to interpolate and serialise.
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $level = $this->normaliseLevel((string) $level);

        // Discard messages below the configured minimum level
        if (!$this->isLoggable($level)) {
            return;
        }

        $message     = $this->interpolate((string) $message, $context);
        $contextJson = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp   = date('Y-m-d H:i:s');
        $levelUpper  = strtoupper($level);

        $line = "[{$timestamp}] [{$levelUpper}] {$message}{$contextJson}" . PHP_EOL;

        // Rotate if the log file has grown too large
        $this->rotateIfNeeded();

        // Append the line to the log file (create if it does not exist)
        file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Interpolate PSR-3 context placeholders in the message.
     *
     * Replaces {key} tokens with the corresponding context value (cast to string).
     *
     * @param string $message Raw message with optional {placeholder} tokens.
     * @param array  $context Associative array of replacements.
     * @return string Interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        if (empty($context) || strpos($message, '{') === false) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_null($value) || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Determine whether the given level meets the configured minimum threshold.
     *
     * @param string $level PSR-3 level string.
     * @return bool True if the message should be recorded.
     */
    private function isLoggable(string $level): bool
    {
        $messagePriority = self::LEVEL_PRIORITY[$level]    ?? 0;
        $minPriority     = self::LEVEL_PRIORITY[$this->minLevel] ?? 0;

        return $messagePriority >= $minPriority;
    }

    /**
     * Check the current log file size and rotate if it exceeds MAX_SIZE_BYTES.
     *
     * Rotation shifts existing numbered files up by one and renames the active
     * log to .1. Files beyond MAX_ROTATIONS are deleted.
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logPath)) {
            return;
        }

        if (filesize($this->logPath) < self::MAX_SIZE_BYTES) {
            return;
        }

        // Shift rotated files: app.log.4 → deleted, app.log.3 → .4, etc.
        for ($i = self::MAX_ROTATIONS - 1; $i >= 1; $i--) {
            $older = $this->logPath . '.' . $i;
            $newer = $this->logPath . '.' . ($i + 1);

            if (file_exists($older)) {
                rename($older, $newer);
            }
        }

        // Move the active log to .1
        rename($this->logPath, $this->logPath . '.1');
    }

    /**
     * Normalise a level string to lowercase and validate it against known levels.
     *
     * Falls back to 'debug' for unrecognised values.
     *
     * @param string $level Raw level string.
     * @return string Normalised PSR-3 level string.
     */
    private function normaliseLevel(string $level): string
    {
        $lower = strtolower(trim($level));

        if (array_key_exists($lower, self::LEVEL_PRIORITY)) {
            return $lower;
        }

        return LogLevel::DEBUG;
    }
}
