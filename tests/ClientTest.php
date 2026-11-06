<?php

declare(strict_types=1);

namespace Veille\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Veille\BatchResult;
use Veille\BinaryResponse;
use Veille\Client;
use Veille\Endpoints;
use Veille\Exception\ApiException;
use Veille\Exception\ConfigurationException;
use Veille\Exception\ResponseDecodingException;
use Veille\Exception\SerializationException;
use Veille\Exception\TransportException;
use Veille\JsonResponse;
use Veille\RequestOptions;

final class ClientTest extends TestCase
{
    private RecordingHttpClient $http;
    private Client $client;

    protected function setUp(): void
    {
        $this->http = new RecordingHttpClient();
        $this->client = new Client('test-key', ['http_client' => $this->http, 'retry_delay' => 0.001]);
    }

    public function testRequiresApiKey(): void
    {
        putenv('VEILLE_API_KEY');
        $this->expectException(ConfigurationException::class);
        new Client();
    }

    public function testRejectsInvalidBaseUrl(): void
    {
        $this->expectException(ConfigurationException::class);
        new Client('k', ['base_url' => 'ftp://example.test']);
    }

    public function testReadsApiKeyFromEnvironment(): void
    {
        putenv('VEILLE_API_KEY=env-key');
        try {
            (new Client(null, ['http_client' => $this->http]))->request('GET', '/v1/usage');
        } finally {
            putenv('VEILLE_API_KEY');
        }
        self::assertSame('env-key', $this->http->requests[0]->getHeaderLine('x-api-key'));
    }

    public function testFeaturedOperation(): void
    {
        $this->http->respondWith(RecordingHttpClient::json(['hello' => 'world']));
        $response = $this->client->intelligenceEmail(['query' => 'jane.doe@example.com']);
        self::assertSame(['hello' => 'world'], $response->data);
        $request = $this->http->requests[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.veille.io/v1/intelligence/email', explode('?', (string) $request->getUri())[0]);
        self::assertSame('test-key', $request->getHeaderLine('x-api-key'));
        self::assertStringStartsWith('veille-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testQuerySerialization(): void
    {
        $client = new Client('k', ['http_client' => $this->http, 'base_url' => 'https://example.test/']);
        $client->request('GET', '/v1/anything', [
            'text' => 'a b&c', 'flag' => false, 'count' => 0, 'ratio' => 1.5, 'whole' => 2.0, 'list' => ['x', 'y'], 'nothing' => null,
        ]);
        self::assertSame(
            'https://example.test/v1/anything?text=a%20b%26c&flag=false&count=0&ratio=1.5&whole=2&list=x&list=y',
            (string) $this->http->requests[0]->getUri(),
        );
    }

    public function testPostSendsJsonBody(): void
    {
        $this->client->request('POST', '/v1/anything', ['query' => 'x', 'page' => 2, 'gone' => null]);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['query' => 'x', 'page' => 2], json_decode((string) $request->getBody(), true));
        self::assertSame('', $request->getUri()->getQuery());
    }

    public function testPostKeepsGlobalParametersInQuery(): void
    {
        $this->client->request('POST', '/v1/anything', ['query' => 'x', 'is_async' => true, 'fields' => 'a']);
        $request = $this->http->requests[0];
        self::assertSame(['query' => 'x'], json_decode((string) $request->getBody(), true));
        self::assertSame('fields=a&is_async=true', $request->getUri()->getQuery());
    }

    public function testPathParameters(): void
    {
        $this->client->request('GET', '/v1/items/{id}', ['id' => 'a/b c', 'other' => 1]);
        self::assertSame('https://api.veille.io/v1/items/a%2Fb%20c?other=1', (string) $this->http->requests[0]->getUri());
        $this->expectException(SerializationException::class);
        $this->client->request('GET', '/v1/items/{id}');
    }

    public function testRequiredParameters(): void
    {
        try {
            $this->client->intelligenceEmail([]); // @phpstan-ignore argument.type
            self::fail('Expected SerializationException');
        } catch (SerializationException) {
        }
        try {
            $this->client->intelligenceEmail(['query' => 'jane.doe@example.com', 'not_a_parameter' => 1]);
            self::fail('Expected SerializationException');
        } catch (SerializationException $exception) {
            self::assertSame('not_a_parameter', $exception->param);
        }
        self::assertSame([], $this->http->requests);
    }

    public function testUnsupportedMethod(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->client->intelligenceDomain(['query' => 'test'], new RequestOptions(method: 'POST'));
    }

    public function testEveryEndpoint(): void
    {
        foreach (Endpoints::ALL as $name => $endpoint) {
            $method = lcfirst(str_replace('_', '', ucwords($name, '_')));
            if (!method_exists($this->client, $method)) {
                $method .= 'Endpoint';
            }
            $this->client->{$method}(self::REQUIRED[$name] ?? []);
            $request = $this->http->requests[\count($this->http->requests) - 1];
            self::assertSame('https://api.veille.io' . str_replace('{id}', 'test', $endpoint['path']), explode('?', (string) $request->getUri())[0], $name);
            self::assertSame($endpoint['methods'][0], $request->getMethod(), $name);
        }
        self::assertCount(\count(Endpoints::ALL), $this->http->requests);
    }

    public function testBinaryResponse(): void
    {
        $this->http->respondWith(new Response(200, ['Content-Type' => 'image/png'], "\x89PNG"));
        $response = $this->client->request('GET', '/v1/anything');
        self::assertInstanceOf(BinaryResponse::class, $response);
        self::assertSame("\x89PNG", $response->bytes);
        self::assertSame('image/png', $response->contentType);
    }

    public function testNonJsonSuccessForJsonOperation(): void
    {
        $this->http->respondWith(new Response(200, ['Content-Type' => 'text/html'], '<html>'));
        $this->expectException(ResponseDecodingException::class);
        $this->client->intelligenceEmail(['query' => 'jane.doe@example.com']);
    }

    public function testApiError(): void
    {
        $this->http->respondWith(RecordingHttpClient::json(['error' => 'Invalid API Key'], 401));
        try {
            $this->client->request('GET', '/v1/usage');
            self::fail('Expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(401, $exception->status);
            self::assertSame('Invalid API Key', $exception->getMessage());
            self::assertSame(['error' => 'Invalid API Key'], $exception->responseBody);
        }
    }

    public function testNonJsonErrorBody(): void
    {
        $this->http->respondWith(new Response(502, [], 'Bad gateway'));
        try {
            $this->client->request('GET', '/v1/usage');
            self::fail('Expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(502, $exception->status);
            self::assertSame('Bad gateway', $exception->rawResponse->body);
        }
    }

    public function testTransportError(): void
    {
        $this->http->failWith(new \RuntimeException('network down'));
        $this->expectException(TransportException::class);
        $this->client->request('GET', '/v1/usage');
    }

    public function testJsonResponseType(): void
    {
        $response = $this->client->request('GET', '/v1/usage');
        self::assertInstanceOf(JsonResponse::class, $response);
    }

    public function testRetriesThenSucceeds(): void
    {
        $this->http->queue(RecordingHttpClient::json(['error' => 'busy'], 503));
        $this->http->queue(new Response(429, ['Retry-After' => '0', 'Content-Type' => 'application/json'], '{}'));
        $this->http->queue(RecordingHttpClient::json(['ok' => true]));
        $response = $this->client->request('GET', '/v1/usage');
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertCount(3, $this->http->requests);
    }

    public function testRetryLimitsAndClientErrors(): void
    {
        $client = new Client('k', ['http_client' => $this->http, 'retry_delay' => 0.001, 'max_retries' => 2]);
        $this->http->respondWith(RecordingHttpClient::json(['error' => 'busy'], 503));
        try {
            $client->request('GET', '/v1/usage');
            self::fail('Expected ApiException');
        } catch (ApiException) {
        }
        self::assertCount(3, $this->http->requests);
        $this->http->respondWith(RecordingHttpClient::json(['error' => 'Payment required'], 402));
        try {
            $client->request('GET', '/v1/usage');
        } catch (ApiException) {
        }
        self::assertCount(4, $this->http->requests);
        $this->http->respondWith(RecordingHttpClient::json([], 500));
        try {
            $client->request('GET', '/v1/usage', [], new RequestOptions(maxRetries: 0));
        } catch (ApiException) {
        }
        self::assertCount(5, $this->http->requests);
    }

    public function testRateLimitsAreRetriedButNotQuotas(): void
    {
        $cases = [
            "Rate limit exceeded: You've exceeded the rate limit on your subscription" => 2,
            'Rate limit exceeded for the API key: quota daily' => 1,
            'Invalid API Key' => 1,
        ];
        foreach ($cases as $message => $expected) {
            $http = new RecordingHttpClient();
            $http->queue(RecordingHttpClient::json(['error' => $message], 401));
            $http->respondWith(RecordingHttpClient::json([]));
            $client = new Client('k', ['http_client' => $http, 'retry_delay' => 0.001]);
            try {
                $client->request('GET', '/v1/usage');
            } catch (ApiException) {
            }
            self::assertCount($expected, $http->requests, $message);
        }
    }

    public function testGlobalParameters(): void
    {
        $this->client->intelligenceEmail(['query' => 'jane.doe@example.com', 'fields' => 'a,b.c', 'is_async' => true, 'soft_fail' => false]);
        parse_str($this->http->requests[0]->getUri()->getQuery(), $query);
        self::assertSame('a,b.c', $query['fields']);
        self::assertSame('true', $query['is_async']);
        self::assertSame('false', $query['soft_fail']);
    }

    public function testBatchWithPsr18Client(): void
    {
        $results = $this->client->batch([
            ['intelligenceEmail', ['query' => 'jane.doe@example.com']],
            ['intelligenceEmail', []],
            ['intelligence_email', ['query' => 'jane.doe@example.com']],
        ]);
        self::assertCount(3, $results);
        self::assertTrue($results[0]->ok());
        self::assertInstanceOf(SerializationException::class, $results[1]->error);
        self::assertTrue($results[2]->ok());
        self::assertSame([0, 1, 2], array_map(static fn(BatchResult $r): int => $r->index, $results));
        self::assertCount(2, $this->http->requests);
    }

    public function testBatchWithGuzzlePoolRetries(): void
    {
        $mock = new MockHandler([
            RecordingHttpClient::json(['n' => 1]),
            RecordingHttpClient::json(['error' => 'busy'], 503),
            RecordingHttpClient::json(['n' => 2]),
        ]);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $client = new Client('k', ['http_client' => $guzzle, 'retry_delay' => 0.001]);
        $results = $client->batch([['intelligenceEmail', ['query' => 'jane.doe@example.com']], ['intelligenceEmail', ['query' => 'jane.doe@example.com']]], 2);
        self::assertTrue($results[0]->ok() && $results[1]->ok());
        self::assertInstanceOf(JsonResponse::class, $results[1]->value);
        self::assertSame(['n' => 2], $results[1]->value->data);
        self::assertSame(0, $mock->count());
    }

    public function testWaitForJob(): void
    {
        foreach (['Queued', 'Running', 'CompletedJob'] as $status) {
            $this->http->queue(RecordingHttpClient::json(['status' => $status, 'progress' => 50]));
        }
        $seen = 0;
        $job = $this->client->waitForJob('abc', 0.001, 60.0, static function () use (&$seen): void {
            ++$seen;
        });
        self::assertIsArray($job->data);
        self::assertSame('CompletedJob', $job->data['status']);
        self::assertCount(3, $this->http->requests);
        self::assertSame('/v1/jobs/abc', $this->http->requests[0]->getUri()->getPath());
        self::assertSame(3, $seen);
    }

    public function testWaitForJobTimesOut(): void
    {
        $this->http->respondWith(RecordingHttpClient::json(['status' => 'Running']));
        $this->expectException(\Veille\Exception\JobTimeoutException::class);
        $this->client->waitForJob('abc', 0.01, 0.001);
    }

    /** @var array<string, array<string, mixed>> */
    private const REQUIRED = [
        'intelligence_domain' => ['query' => 'test'],
        'intelligence_email' => ['query' => 'john@example.com'],
        'intelligence_emailq' => ['query' => 'john@example.com'],
        'intelligence_ip' => ['query' => 'test'],
        'intelligence_phone' => ['query' => '+33612345678'],
        'jobs' => ['id' => 'test'],
        'vat' => ['query' => 'test'],
        'vat_iban' => ['query' => 'test'],
    ];
}
