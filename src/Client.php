<?php

declare(strict_types=1);

namespace Veille;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Veille\Exception\ApiException;
use Veille\Exception\ConfigurationException;
use Veille\Exception\JobTimeoutException;
use Veille\Exception\ResponseDecodingException;
use Veille\Exception\SerializationException;
use Veille\Exception\TransportException;
use Veille\Exception\VeilleException;
use Veille\Internal\Serializer;

/**
 * Official Veille API client. Create one instance and reuse it.
 */
final class Client
{
    use Operations;

    public const DEFAULT_BASE_URL = 'https://api.veille.io';
    public const DEFAULT_TIMEOUT = 120.0;
    /** Requests in flight at the same time in batch(). */
    public const DEFAULT_CONCURRENCY = 10;
    /** Retries after a network error or a 408, 429, 500, 502, 503 or 504 response. */
    public const MAX_RETRIES = 3;
    /** Each retry waits this many times longer than the previous one (a Retry-After header wins). */
    public const RETRY_BACKOFF_FACTOR = 1.5;
    /** Seconds before the first retry. */
    public const DEFAULT_RETRY_DELAY = 1.0;
    public const MAX_RETRY_DELAY = 30.0;
    public const RETRYABLE_STATUSES = [408, 500, 502, 503, 504];
    public const API_KEY_ENVIRONMENT_VARIABLE = 'VEILLE_API_KEY';
    public const JOB_TERMINAL_STATUSES = ['CompletedJob', 'Error', 'NotFound', 'BadRequest'];
    private const ERROR_MESSAGE_KEYS = ['error', 'message', 'error_message', 'detail'];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $maxRetries;
    private readonly float $retryDelaySeconds;
    private readonly ClientInterface $httpClient;

    /**
     * @param string|null $apiKey defaults to the VEILLE_API_KEY environment variable
     * @param array{base_url?: string, timeout?: float, headers?: array<string, string>, max_retries?: int, retry_delay?: float, http_client?: ClientInterface} $options
     *        `http_client` is any PSR-18 client; the default Guzzle client uses `timeout` (seconds) and never follows redirects
     */
    public function __construct(?string $apiKey = null, private readonly array $options = [])
    {
        $apiKey ??= self::readEnvironment();
        if ($apiKey === null || trim($apiKey) === '') {
            throw new ConfigurationException(
                'A non-blank Veille API key is required. Pass it to the constructor or set ' . self::API_KEY_ENVIRONMENT_VARIABLE . '.',
            );
        }
        if (preg_match('/[\r\n]/', $apiKey) === 1) {
            throw new ConfigurationException('The Veille API key cannot be used in an HTTP header.');
        }
        $baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (!\in_array($scheme, ['http', 'https'], true) || parse_url($baseUrl, PHP_URL_HOST) === null) {
            throw new ConfigurationException('base_url must be an absolute http or https URL.');
        }
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        if ($timeout <= 0) {
            throw new ConfigurationException('timeout must be a positive number of seconds.');
        }
        $maxRetries = $options['max_retries'] ?? self::MAX_RETRIES;
        if ($maxRetries < 0) {
            throw new ConfigurationException('max_retries must be a non-negative integer.');
        }
        $retryDelay = $options['retry_delay'] ?? self::DEFAULT_RETRY_DELAY;
        if ($retryDelay < 0) {
            throw new ConfigurationException('retry_delay must be a non-negative number of seconds.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->maxRetries = $maxRetries;
        $this->retryDelaySeconds = (float) $retryDelay;
        $this->httpClient = $options['http_client'] ?? new GuzzleClient([
            'timeout' => $timeout,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }

    /**
     * Sends any request, including endpoints released after this SDK version.
     * GET parameters travel in the query string; POST parameters travel in a JSON body.
     *
     * @param 'GET'|'POST' $method
     * @param array<string, mixed> $params
     */
    public function request(string $method, string $path, array $params = [], ?RequestOptions $options = null): JsonResponse|BinaryResponse
    {
        return $this->send(strtoupper($method), $path, $params, $options, 'any');
    }

    /**
     * Polls `GET /v1/jobs/{id}` until the job reaches a terminal status. The delay starts at `$interval`
     * seconds and grows 1.5x per poll up to 60 s; `$timeout` defaults to 12 hours.
     *
     * @param (callable(mixed): void)|null $onProgress called with the job document after every poll
     */
    public function waitForJob(string $jobId, float $interval = 3.0, float $timeout = 43_200.0, ?callable $onProgress = null): JsonResponse
    {
        if (trim($jobId) === '') {
            throw new SerializationException('A non-blank job id is required.', 'id');
        }
        $started = microtime(true);
        while (true) {
            $last = $this->request('GET', '/v1/jobs/{id}', ['id' => $jobId]);
            if (!$last instanceof JsonResponse) {
                throw new ResponseDecodingException('The job endpoint did not return JSON.', $last->rawResponse);
            }
            if ($onProgress !== null) {
                $onProgress($last->data);
            }
            if (\is_array($last->data) && \in_array($last->data['status'] ?? null, self::JOB_TERMINAL_STATUSES, true)) {
                return $last;
            }
            if (microtime(true) - $started + $interval > $timeout) {
                throw new JobTimeoutException($jobId, $last);
            }
            usleep((int) ($interval * 1_000_000));
            $interval = min($interval * 1.5, max($interval, 60.0));
        }
    }

    /**
     * Runs several endpoint calls with at most `$concurrency` requests in flight (default 10) and returns one
     * {@see BatchResult} per call, in input order. A failing call never stops the others. Each call is
     * `[method, params]`, e.g. `['intelligenceEmail', ['query' => 'jane.doe@example.com']]`; the method is an SDK method name or an endpoint
     * name. Requests run concurrently with the default Guzzle client and one after another with other PSR-18
     * clients; retries apply in both cases.
     *
     * @param iterable<array{0: string, 1?: array<string, mixed>}> $calls
     *
     * @return list<BatchResult>
     */
    public function batch(iterable $calls, int $concurrency = self::DEFAULT_CONCURRENCY): array
    {
        if ($concurrency < 1) {
            throw new ConfigurationException('concurrency must be a positive integer.');
        }
        /** @var array<int, BatchResult> $results */
        $results = [];
        /** @var array<int, array{0: string, 1: string, 2: array<string, string>, 3: ?string, 4: string}> $pending */
        $pending = [];
        $index = 0;
        foreach ($calls as $call) {
            $i = $index++;
            try {
                $name = Endpoints::METHODS[$call[0]] ?? $call[0];
                [$method, $path, $expected] = $this->resolveOperation($name, $call[1] ?? [], null);
                $pending[$i] = [...$this->prepareRequest($method, $path, $call[1] ?? [], null), $expected];
            } catch (VeilleException $exception) {
                $results[$i] = new BatchResult($i, null, $exception);
            }
        }

        for ($attempt = 0; $pending !== []; ++$attempt) {
            $outcomes = $this->sendAll($pending, $concurrency);
            $retry = [];
            $retryAfter = null;
            foreach ($pending as $i => $request) {
                $outcome = $outcomes[$i];
                if ($this->shouldRetry($outcome, $attempt, $this->maxRetries)) {
                    $retry[$i] = $request;
                    if ($outcome instanceof RawResponse && isset($outcome->headers['retry-after'][0])) {
                        $retryAfter = $outcome->headers['retry-after'][0];
                    }
                    continue;
                }
                try {
                    $results[$i] = new BatchResult($i, $this->finish($outcome, $request[4]));
                } catch (VeilleException $exception) {
                    $results[$i] = new BatchResult($i, null, $exception);
                }
            }
            if ($retry !== []) {
                usleep((int) ($this->retryDelay($attempt, $retryAfter) * 1_000_000));
            }
            $pending = $retry;
        }
        ksort($results);

        return array_values($results);
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function callOperation(string $name, array $params, ?RequestOptions $options): JsonResponse|BinaryResponse
    {
        [$method, $path, $expected] = $this->resolveOperation($name, $params, $options);

        return $this->send($method, $path, $params, $options, $expected);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveOperation(string $name, array $params, ?RequestOptions $options): array
    {
        if (!isset(Endpoints::ALL[$name])) {
            throw new ConfigurationException(sprintf('Unknown endpoint "%s".', $name));
        }
        $endpoint = Endpoints::ALL[$name];
        $method = $options->method ?? $endpoint['methods'][0];
        if (!\in_array($method, $endpoint['methods'], true)) {
            throw new ConfigurationException(sprintf('%s supports %s, not %s.', $endpoint['path'], implode(', ', $endpoint['methods']), $method));
        }
        $unknown = array_diff(array_keys($params), $endpoint['params']);
        if ($unknown !== []) {
            throw new SerializationException(
                sprintf('Unknown parameter(s) for %s: %s. Use request() for undocumented parameters.', $name, implode(', ', $unknown)),
                (string) reset($unknown),
            );
        }
        foreach ($endpoint['required'] as $required) {
            if (!isset($params[$required])) {
                throw new SerializationException(sprintf('The "%s" parameter is required.', $required), $required);
            }
        }

        return [$method, $endpoint['path'], $endpoint['response']];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{0: string, 1: string, 2: array<string, string>, 3: ?string}
     */
    private function prepareRequest(string $method, string $path, array $params, ?RequestOptions $options): array
    {
        if ($method !== 'GET' && $method !== 'POST') {
            throw new ConfigurationException(sprintf('Unsupported HTTP method: %s.', $method));
        }
        if (!str_starts_with($path, '/')) {
            throw new ConfigurationException('The request path must start with "/".');
        }
        [$filledPath, $query, $body] = Serializer::serialize($method, $path, $params);
        $url = $this->baseUrl . $filledPath . ($query === [] ? '' : '?' . Serializer::encodeQuery($query));
        $headers = array_merge(
            [
                'Accept' => 'application/json, */*;q=0.8',
                'User-Agent' => 'veille-php/' . Version::VERSION,
            ],
            $this->options['headers'] ?? [],
            $options->headers ?? [],
            ['x-api-key' => $this->apiKey],
        );
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = Serializer::encodeJson($body);
        }

        return [$method, $url, $headers, $payload];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function send(string $method, string $path, array $params, ?RequestOptions $options, string $expected): JsonResponse|BinaryResponse
    {
        [$method, $url, $headers, $payload] = $this->prepareRequest($method, $path, $params, $options);
        $maxRetries = $options->maxRetries ?? $this->maxRetries;
        for ($attempt = 0; ; ++$attempt) {
            try {
                $outcome = self::rawResponse($this->httpClient->sendRequest(new Request($method, $url, $headers, $payload)));
            } catch (ClientExceptionInterface|\RuntimeException $exception) {
                $outcome = $exception;
            }
            if (!$this->shouldRetry($outcome, $attempt, $maxRetries)) {
                return $this->finish($outcome, $expected);
            }
            $retryAfter = $outcome instanceof RawResponse ? ($outcome->headers['retry-after'][0] ?? null) : null;
            usleep((int) ($this->retryDelay($attempt, $retryAfter) * 1_000_000));
        }
    }

    private function shouldRetry(RawResponse|\Throwable $outcome, int $attempt, int $maxRetries): bool
    {
        if ($attempt >= $maxRetries) {
            return false;
        }
        if ($outcome instanceof RawResponse) {
            if (\in_array($outcome->status, self::RETRYABLE_STATUSES, true)) {
                return true;
            }
            if ($outcome->status !== 401 && $outcome->status !== 429) {
                return false;
            }
            // Plan rate limits come back as 401/429 "Rate limit exceeded"; per-key quotas ("quota daily") are not retried.
            $text = strtolower($this->apiException($outcome)->errorMessage ?? '');
            if (str_contains($text, 'quota')) {
                return false;
            }

            return str_contains($text, 'rate limit') || ($outcome->status === 429 && $text === '');
        }
        $message = strtolower($outcome->getMessage());

        return !str_contains($message, 'timed out') && !str_contains($message, 'curl error 28');
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: array<string, string>, 3: ?string, 4: string}> $requests
     *
     * @return array<int, RawResponse|\Throwable>
     */
    private function sendAll(array $requests, int $concurrency): array
    {
        $outcomes = [];
        if (!$this->httpClient instanceof GuzzleClientInterface) {
            foreach ($requests as $i => [$method, $url, $headers, $payload]) {
                try {
                    $outcomes[$i] = self::rawResponse($this->httpClient->sendRequest(new Request($method, $url, $headers, $payload)));
                } catch (ClientExceptionInterface|\RuntimeException $exception) {
                    $outcomes[$i] = $exception;
                }
            }

            return $outcomes;
        }
        $generator = static function () use ($requests): \Generator {
            foreach ($requests as $i => [$method, $url, $headers, $payload]) {
                yield $i => new Request($method, $url, $headers, $payload);
            }
        };
        $pool = new Pool($this->httpClient, $generator(), [
            'concurrency' => $concurrency,
            'options' => ['http_errors' => false, 'allow_redirects' => false],
            'fulfilled' => static function (ResponseInterface $response, int $i) use (&$outcomes): void {
                $outcomes[$i] = self::rawResponse($response);
            },
            'rejected' => static function (mixed $reason, int $i) use (&$outcomes): void {
                $outcomes[$i] = $reason instanceof \Throwable ? $reason : new \RuntimeException('Request rejected.');
            },
        ]);
        $pool->promise()->wait();

        return $outcomes;
    }

    private function finish(RawResponse|\Throwable $outcome, string $expected): JsonResponse|BinaryResponse
    {
        if ($outcome instanceof \Throwable) {
            throw new TransportException('Veille request failed before an HTTP response was received.', $outcome);
        }
        if ($outcome->status < 200 || $outcome->status > 299) {
            throw $this->apiException($outcome);
        }

        return $this->decode($outcome, $expected);
    }

    private static function rawResponse(ResponseInterface $response): RawResponse
    {
        return new RawResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            array_map(array_values(...), array_change_key_case($response->getHeaders(), CASE_LOWER)),
            $response->hasHeader('Content-Type') ? $response->getHeaderLine('Content-Type') : null,
            (string) $response->getBody(),
        );
    }

    private function retryDelay(int $attempt, ?string $retryAfter): float
    {
        if ($retryAfter !== null && is_numeric($retryAfter) && (float) $retryAfter >= 0) {
            return min((float) $retryAfter, 60.0);
        }

        return min($this->retryDelaySeconds * self::RETRY_BACKOFF_FACTOR ** $attempt, self::MAX_RETRY_DELAY);
    }

    private function apiException(RawResponse $raw): ApiException
    {
        $decoded = self::decodeJson($raw->body);
        $message = null;
        if ($decoded['ok'] && \is_array($decoded['value'])) {
            foreach (self::ERROR_MESSAGE_KEYS as $key) {
                if (isset($decoded['value'][$key]) && \is_string($decoded['value'][$key]) && $decoded['value'][$key] !== '') {
                    $message = $decoded['value'][$key];
                    break;
                }
            }
        }

        return new ApiException($raw, $decoded['ok'] ? $decoded['value'] : null, $message);
    }

    private function decode(RawResponse $raw, string $expected): JsonResponse|BinaryResponse
    {
        if (!self::isJsonMediaType($raw->contentType)) {
            if ($expected === 'json') {
                throw new ResponseDecodingException('Veille returned a successful non-JSON response for a JSON operation.', $raw);
            }

            return new BinaryResponse($raw->body, $raw->contentType, $raw);
        }
        $decoded = self::decodeJson($raw->body);
        if (!$decoded['ok']) {
            throw new ResponseDecodingException('Veille returned a successful JSON response that could not be decoded.', $raw);
        }

        return new JsonResponse($decoded['value'], $raw);
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private static function decodeJson(string $body): array
    {
        try {
            return ['ok' => true, 'value' => json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
        } catch (\JsonException) {
            return ['ok' => false, 'value' => null];
        }
    }

    private static function isJsonMediaType(?string $contentType): bool
    {
        if ($contentType === null) {
            return false;
        }
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
    }

    private static function readEnvironment(): ?string
    {
        $value = getenv(self::API_KEY_ENVIRONMENT_VARIABLE);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
