<?php

declare(strict_types=1);

namespace Veille;

/**
 * A successful non-JSON response (image, PDF, HTML…).
 */
final class BinaryResponse
{
    public function __construct(
        public readonly string $bytes,
        public readonly ?string $contentType,
        public readonly RawResponse $rawResponse,
    ) {}

    /**
     * Writes the body to `$path` and returns the number of bytes written.
     */
    public function save(string $path): int
    {
        $written = file_put_contents($path, $this->bytes);
        if ($written === false) {
            throw new \RuntimeException(sprintf('Could not write %s.', $path));
        }

        return $written;
    }
}
