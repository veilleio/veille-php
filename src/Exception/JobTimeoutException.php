<?php

declare(strict_types=1);

namespace Veille\Exception;

use Veille\JsonResponse;

/**
 * An async job did not reach a terminal status before the deadline.
 */
final class JobTimeoutException extends VeilleException
{
    public function __construct(public readonly string $jobId, public readonly ?JsonResponse $lastResponse = null)
    {
        parent::__construct(sprintf('Job %s did not finish before the deadline.', $jobId));
    }
}
