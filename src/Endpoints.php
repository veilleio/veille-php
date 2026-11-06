<?php

declare(strict_types=1);

namespace Veille;

// Generated from the Veille OpenAPI document. Do not edit by hand.

/**
 * Path, methods, response kind and parameter names of every documented operation.
 */
final class Endpoints
{
    /**
     * @var array<string, array{path: string, methods: list<'GET'|'POST'>, response: 'json'|'any', required: list<string>, params: list<string>}>
     */
    public const ALL = [
        'intelligence_domain' => [
            'path' => '/v1/intelligence/domain',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'certificate_transparency', 'fields', 'is_async', 'soft_fail'],
        ],
        'intelligence_email' => [
            'path' => '/v1/intelligence/email',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'intelligence_emailq' => [
            'path' => '/v1/intelligence/emailq',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'intelligence_ip' => [
            'path' => '/v1/intelligence/ip',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'intelligence_phone' => [
            'path' => '/v1/intelligence/phone',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'jobs' => [
            'path' => '/v1/jobs/{id}',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['id'],
            'params' => ['id'],
        ],
        'stats' => [
            'path' => '/v1/stats',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => [],
            'params' => ['engine', 'path'],
        ],
        'usage' => [
            'path' => '/v1/usage',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => [],
            'params' => [],
        ],
        'vat' => [
            'path' => '/v1/vat',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'vat_iban' => [
            'path' => '/v1/vat/iban',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => ['query'],
            'params' => ['query', 'fields', 'is_async', 'soft_fail'],
        ],
        'vat_rates' => [
            'path' => '/v1/vat/rates',
            'methods' => ['GET'],
            'response' => 'json',
            'required' => [],
            'params' => ['base', 'symbols', 'fields', 'is_async', 'soft_fail'],
        ],
    ];

    /**
     * SDK method name to endpoint name, for batch().
     *
     * @var array<string, string>
     */
    public const METHODS = [
        'intelligenceDomain' => 'intelligence_domain',
        'intelligenceEmail' => 'intelligence_email',
        'intelligenceEmailq' => 'intelligence_emailq',
        'intelligenceIp' => 'intelligence_ip',
        'intelligencePhone' => 'intelligence_phone',
        'jobs' => 'jobs',
        'stats' => 'stats',
        'usage' => 'usage',
        'vat' => 'vat',
        'vatIban' => 'vat_iban',
        'vatRates' => 'vat_rates',
    ];
}
