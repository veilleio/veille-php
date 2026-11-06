<?php

declare(strict_types=1);

namespace Veille;

/**
 * Per-request options.
 */
final class RequestOptions
{
    /**
     * @param 'GET'|'POST'|null $method GET (default when supported) sends query parameters; POST sends a JSON body
     * @param array<string, string> $headers extra headers for this request
     * @param int|null $maxRetries overrides the client retry count for this request (0 disables retries)
     */
    public function __construct(
        public readonly ?string $method = null,
        public readonly array $headers = [],
        public readonly ?int $maxRetries = null,
    ) {}
}
