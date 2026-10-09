<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Throwable;

class ProductionLogTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter);
        }
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $context = [];
            foreach (['event', 'request_id', 'route', 'method', 'status', 'duration_ms', 'exception_type', 'file', 'line', 'job', 'offsite', 'archive_bytes', 'database', 'cache', 'storage', 'queue', 'scheduler', 'backup'] as $key) {
                $value = $record->context[$key] ?? null;
                if (is_scalar($value) || $value === null) {
                    $context[$key] = $value;
                }
            }
            $exception = $record->context['exception'] ?? null;
            if ($exception instanceof Throwable) {
                $context['exception_type'] = $exception::class;
                $context['file'] = basename($exception->getFile());
                $context['line'] = $exception->getLine();
                $context['trace'] = array_map(fn (array $frame): array => array_filter(['file' => isset($frame['file']) ? basename($frame['file']) : null, 'line' => $frame['line'] ?? null, 'class' => $frame['class'] ?? null, 'function' => $frame['function'] ?? null]), array_slice($exception->getTrace(), 0, 20));
            }

            return $record->with(message: $record->context['event'] ?? ($record->level->value >= 400 ? 'application.error' : 'application.log'), context: array_filter($context, fn (mixed $value): bool => $value !== null), extra: []);
        });
    }
}
