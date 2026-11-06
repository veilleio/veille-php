<?php

declare(strict_types=1);

namespace Veille;

/**
 * Outcome of one batch() call: `value` when ok(), else `error`.
 */
final class BatchResult
{
    public function __construct(
        public readonly int $index,
        public readonly JsonResponse|BinaryResponse|null $value = null,
        public readonly ?\Throwable $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }
}
