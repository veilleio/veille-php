# Veille PHP SDK

The official PHP SDK for the [Veille](https://www.veille.io) fraud prevention API.

Validate emails, domains, IPs, VAT numbers and IBANs in milliseconds to block fake sign-ups, bots and fraud.

The SDK exposes **11 methods**, one per documented endpoint (with array-shape PHPDoc for static analysis), plus a generic `request()`. It sends requests through any PSR-18 client and uses Guzzle by default.

## Requirements

- PHP 8.1 or newer

## Installation

```sh
composer require veille/sdk
```

## Get your API key

[Sign up for Veille](https://app.veille.io/register) or sign in, then copy your API key from the [dashboard](https://app.veille.io). The client reads the `VEILLE_API_KEY` environment variable when no key is passed.

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Veille\Client;

$client = new Client(getenv('VEILLE_API_KEY') ?: null);
$response = $client->intelligenceEmail(['query' => 'jane.doe@example.com']);
print_r($response->data);
```

Parameter arrays use the names of the [API reference](https://app.veille.io/openapi). Unknown names throw a `SerializationException`; use `request()` for undocumented parameters.

## Async jobs

Any call accepts `is_async: true` (useful past the 60-second server limit): the response is then a `job_id`. `waitForJob()` polls `GET /v1/jobs/{id}` until a terminal status (`CompletedJob`, `Error`, `NotFound`, `BadRequest`):

```php
$submitted = $client->intelligenceEmail(['query' => 'jane.doe@example.com', 'is_async' => true]);
$job = $client->waitForJob($submitted->data['job_id'], onProgress: static function (array $job): void {
    echo $job['status'], ' ', $job['progress'] ?? 0, "%\n";
});
print_r($job->data);
```

## GET or POST

Methods use GET when the endpoint supports it (query string). POST sends the same parameters as JSON:

`null` values are omitted, `false` and `0` are sent, and lists repeat the parameter name.

## Any endpoint

```php
$response = $client->request('GET', '/v1/usage');
```

## Errors

```php
use Veille\Exception\ApiException;

try {
    $client->intelligenceEmail(['query' => 'jane.doe@example.com']);
} catch (ApiException $exception) {
    echo $exception->status, ': ', $exception->getMessage();
}
```

| Exception | When |
| --- | --- |
| `ConfigurationException` | Missing API key, invalid options or unsupported method. |
| `SerializationException` | A required parameter is missing, unknown or can't be sent. |
| `TransportException` | Network failure or timeout. |
| `ApiException` | Non-2xx status (`status`, `errorMessage`, `responseBody`, `rawResponse`). |
| `ResponseDecodingException` | A success response could not be decoded. |

All of them extend `Veille\Exception\VeilleException`.

## Retries

Requests are retried up to 3 times after a network error, a `408`, `500`, `502`, `503` or `504`, or a rate limit — which the API returns as `401` (or `429`) with a `Rate limit exceeded` message. Per-key quota errors (`…: quota daily`) are not retried, nor are `400`, `402`, `404` or client-side timeouts. The delay starts at 1 second and grows 1.5× per retry (capped at 30 seconds); a `Retry-After` header wins.

```php
$client = new Client(null, ['max_retries' => 5, 'retry_delay' => 0.5]);
$client->intelligenceEmail(['query' => 'jane.doe@example.com'], new RequestOptions(maxRetries: 0)); // one request without retries
```

## Batch requests

`batch()` sends several calls with at most 10 requests in flight (`$concurrency`), through Guzzle's connection pool, and returns one `BatchResult` per call in input order. A failing call never stops the others. With a custom PSR-18 client the calls run one after another.

```php
$inputs = ['jane.doe@example.com', 'john@mailinator.com', 'contact@veille.io'];
$calls = array_map(static fn(string $value): array => ['intelligenceEmail', ['query' => $value]], $inputs);

foreach ($client->batch($calls, concurrency: 5) as $result) {
    if ($result->ok()) {
        print_r($result->value->data);
    } else {
        echo $inputs[$result->index], ': ', $result->error->getMessage(), "\n";
    }
}
```

| Constant | Default | Description |
| --- | --- | --- |
| `Client::DEFAULT_CONCURRENCY` | `10` | Tasks run at the same time by batch |
| `Client::DEFAULT_TIMEOUT` | `120.0` | Request timeout (seconds) |
| `Client::MAX_RETRIES` | `3` | Retry attempts after a network error, a 408/5xx or a rate limit |
| `Client::RETRY_BACKOFF_FACTOR` | `1.5` | Exponential backoff multiplier |
| `Client::DEFAULT_RETRY_DELAY` | `1.0` | Delay before the first retry (seconds) |

## Options and custom HTTP clients

```php
$client = new Client(null, [
    'timeout' => 60.0,               // seconds, default 120 (default Guzzle client only)
    'headers' => ['X-Trace' => 'abc'],
    'http_client' => $psr18Client,   // optional: any PSR-18 client
]);
```

## Endpoints

11 methods, generated from the [OpenAPI document](https://app.veille.io/openapi).

| Method | HTTP | Description |
| --- | --- | --- |
| `intelligenceDomain()` | GET `/v1/intelligence/domain` | Check any domain instantly. Detects disposable email providers, public webmail, relay services, and education domains. Returns DNS health w… |
| `intelligenceEmail()` | GET `/v1/intelligence/email` | Validate any email address in a single call. Detects disposable and temporary inboxes, identifies role accounts, public webmail, relay serv… |
| `intelligenceEmailq()` | GET `/v1/intelligence/emailq` | Deep email deliverability verification. Performs full Email Validation plus SMTP inbox check, catch-all detection, and returns a qualificat… |
| `intelligenceIp()` | GET `/v1/intelligence/ip` | Assess the reputation of any IP address. Detects VPNs, proxies, Tor exit nodes, datacenter hosting, abusive traffic, and bots. Returns geol… |
| `intelligencePhone()` | GET `/v1/intelligence/phone` | Validate any phone number in E.164 format. Detects disposable and virtual numbers, identifies carrier and line type (mobile/fixed/VoIP), re… |
| `jobs()` | GET `/v1/jobs/{id}` | Poll the status, progress (0–100) and result of a job started with is_async=true. Polling is free. Poll until status is terminal: Completed… |
| `stats()` | GET `/v1/stats` | Infrastructure health for an engine or endpoint over the last hour. Not scoped to your account or tenant. Enabled per account: HTTP 403 mea… |
| `usage()` | GET `/v1/usage` | Retrieve real-time usage statistics and credit balance for your Veille account. |
| `vat()` | GET `/v1/vat` | Validate a European VAT number against the VIES database. Returns company name, registered address, and validity status for any EU member s… |
| `vatIban()` | GET `/v1/vat/iban` | Validate any IBAN and retrieve associated bank details. Returns the BIC/SWIFT code, bank name, country, BBAN structural breakdown, and SEPA… |
| `vatRates()` | GET `/v1/vat/rates` | Get current ECB exchange rates for any supported base currency. Optionally filter to specific target currencies. Updated daily from the Eur… |

## License

MIT — see [LICENSE](LICENSE).
