<?php

declare(strict_types=1);

namespace Veille;

/**
 * A successful JSON response; `data` is the decoded body (associative arrays).
 */
final class JsonResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly RawResponse $rawResponse,
    ) {}
}
