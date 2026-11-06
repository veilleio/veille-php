<?php

declare(strict_types=1);

namespace Veille;

/**
 * Buffered HTTP response: status, lower-cased multi-value headers, content type and body bytes.
 */
final class RawResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $statusText,
        public readonly array $headers,
        public readonly ?string $contentType,
        public readonly string $body,
    ) {}
}
