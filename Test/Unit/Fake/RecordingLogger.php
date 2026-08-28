<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Fake;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps what it was told.
 */
class RecordingLogger extends AbstractLogger
{
    /** @var array<int, array{message: string, context: array<string, mixed>}> */
    public array $errors = [];

    /** @var array<int, array{message: string, context: array<string, mixed>}> */
    public array $infos = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $entry = ['message' => (string) $message, 'context' => $context];

        if (in_array((string) $level, ['error', 'critical', 'alert', 'emergency'], true)) {
            $this->errors[] = $entry;
        }

        if ((string) $level === 'info') {
            $this->infos[] = $entry;
        }
    }
}
