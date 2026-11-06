<?php

declare(strict_types=1);

namespace Veille\Tests;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that records requests and replays canned responses.
 */
final class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $queue = [];

    private ?ResponseInterface $default = null;

    private ?\Throwable $failure = null;

    /**
     * @param array<mixed> $body
     */
    public static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function respondWith(ResponseInterface $response): void
    {
        $this->default = $response;
    }

    public function queue(ResponseInterface $response): void
    {
        $this->queue[] = $response;
    }

    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return array_shift($this->queue) ?? $this->default ?? self::json(['ok' => true]);
    }
}
