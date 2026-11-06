<?php

declare(strict_types=1);

namespace Veille;

// Generated from the Veille OpenAPI document. Do not edit by hand.

/**
 * One method per documented Veille endpoint. Used by {@see Client}.
 */
trait Operations
{
    /**
     * @param array<string, mixed> $params
     */
    abstract protected function callOperation(string $name, array $params, ?RequestOptions $options): JsonResponse|BinaryResponse;

    /**
     * Domain Validation. Check any domain instantly. Detects disposable email providers, public webmail, relay services, and education domains. Returns DNS health with MX provider detection, domain history, and optional SSL/TLS certificate transparency. Credit cost: 2 credits per call.
     *
     * `GET /v1/intelligence/domain`
     *
     * @param array{query: string, certificate_transparency?: bool, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The domain to validate. Accepts raw domains (tempmail.org), full URLs (https://www.tempmail.org/page), or domains with www. prefix, all are normalized automati…
     *   - certificate_transparency: Enable Certificate Transparency lookup. Returns SSL/TLS certificate history, current certificate, issuers, and all Subject Alternative Names (SANs) ever associ…
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function intelligenceDomain(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('intelligence_domain', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Email Validation. Validate any email address in a single call. Detects disposable and temporary inboxes, identifies role accounts, public webmail, relay services, and returns a 0-100 risk score. For deep deliverability verification (SMTP check, catch-all de… Credit cost: 3 credits per call.
     *
     * `GET /v1/intelligence/email`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The email address to validate (e.g. john@example.com). Automatically normalized to lowercase.
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function intelligenceEmail(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('intelligence_email', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Email Qualification. Deep email deliverability verification. Performs full Email Validation plus SMTP inbox check, catch-all detection, and returns a qualification verdict (valid, invalid, risky, disposable, unknown). Credit cost: 5 credits per call.
     *
     * `GET /v1/intelligence/emailq`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The email address to qualify (e.g. john@example.com). Automatically normalized to lowercase.
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function intelligenceEmailq(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('intelligence_emailq', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * IP Reputation. Assess the reputation of any IP address. Detects VPNs, proxies, Tor exit nodes, datacenter hosting, abusive traffic, and bots. Returns geolocation, network owner, and a 0-100 threat score. Credit cost: 2 credits per call.
     *
     * `GET /v1/intelligence/ip`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The IP address to analyze (IPv4 or IPv6).
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function intelligenceIp(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('intelligence_ip', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Phone Validation. Validate any phone number in E.164 format. Detects disposable and virtual numbers, identifies carrier and line type (mobile/fixed/VoIP), returns country and timezone, and computes a 0-100 risk score. Credit cost: 1 credit per call.
     *
     * `GET /v1/intelligence/phone`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The phone number to validate in E.164 format (must start with +, e.g. +33612345678).
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function intelligencePhone(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('intelligence_phone', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Jobs. Poll the status, progress (0–100) and result of a job started with is_async=true. Polling is free. Poll until status is terminal: CompletedJob, Error, NotFound or BadRequest.
     *
     * `GET /v1/jobs/{id}`
     *
     * @param array{id: string} $params
     *   - id (required): The job_id returned by an endpoint called with is_async=true.
     */
    public function jobs(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('jobs', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Stats. Infrastructure health for an engine or endpoint over the last hour. Not scoped to your account or tenant. Enabled per account: HTTP 403 means contact support to activate it.
     *
     * `GET /v1/stats`
     *
     * @param array{engine?: string, path?: string} $params
     *   - engine: API family to check, for example vat. Provide at least engine or path.
     *   - path: Specific endpoint to check, for example /v1/vat. If you also pass engine, use an endpoint from that API.
     */
    public function stats(array $params = [], ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('stats', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Usage. Retrieve real-time usage statistics and credit balance for your Veille account.
     *
     * `GET /v1/usage`
     *
     * @param array{} $params
     */
    public function usage(array $params = [], ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('usage', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * VAT Validation. Validate a European VAT number against the VIES database. Returns company name, registered address, and validity status for any EU member state. Credit cost: 1 credit per call.
     *
     * `GET /v1/vat`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The VAT number to validate (e.g. FR40303265045).
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function vat(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('vat', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * IBAN Validation. Validate any IBAN and retrieve associated bank details. Returns the BIC/SWIFT code, bank name, country, BBAN structural breakdown, and SEPA zone membership. Credit cost: 1 credit per call.
     *
     * `GET /v1/vat/iban`
     *
     * @param array{query: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - query (required): The IBAN to validate (e.g. FR7630006000011234567890189).
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function vatIban(array $params, ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('vat_iban', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }

    /**
     * Exchange Rates. Get current ECB exchange rates for any supported base currency. Optionally filter to specific target currencies. Updated daily from the European Central Bank. Credit cost: 1 credit per call.
     *
     * `GET /v1/vat/rates`
     *
     * @param array{base?: string, symbols?: string, fields?: string, is_async?: bool, soft_fail?: bool} $params
     *   - base: Base currency ISO 4217 code (defaults to EUR).
     *   - symbols: Comma-separated target currency codes to filter (e.g. USD,GBP,JPY).
     *   - fields: Comma-separated list of fields to keep in the response, with dot notation for nested fields (e.g. name,location.city). Does not change the credit cost.
     *   - is_async: Process the request asynchronously: the response is a job_id to poll on /v1/jobs/{id} (see waitForJob).
     *   - soft_fail: Return HTTP 200 even when an error occurs; the error is then in the response body.
     */
    public function vatRates(array $params = [], ?RequestOptions $options = null): JsonResponse
    {
        $response = $this->callOperation('vat_rates', $params, $options);
        \assert($response instanceof JsonResponse);

        return $response;
    }
}
