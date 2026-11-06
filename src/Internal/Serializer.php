<?php

declare(strict_types=1);

namespace Veille\Internal;

use Veille\Exception\SerializationException;

/**
 * @internal
 */
final class Serializer
{
    /**
     * Fills `{name}` path segments, then returns the query pairs (GET) or JSON body (POST).
     * On POST the global parameters still go in the query string. `null` values are omitted; `false` and `0` are sent.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: string, 1: list<array{0: string, 1: string}>, 2: array<string, mixed>|null}
     */
    public static function serialize(string $method, string $pathTemplate, array $params): array
    {
        $remaining = array_filter($params, static fn(mixed $value): bool => $value !== null);
        $path = preg_replace_callback('/\{([^}]+)\}/', static function (array $match) use (&$remaining): string {
            $name = $match[1];
            if (!\array_key_exists($name, $remaining)) {
                throw new SerializationException(sprintf('The "%s" path parameter is required.', $name), $name);
            }
            $value = self::toWireString($name, $remaining[$name]);
            unset($remaining[$name]);

            return rawurlencode($value);
        }, $pathTemplate);
        \assert(\is_string($path));

        if ($method === 'POST') {
            // The API reads the global parameters from the query string, whatever the method.
            $globals = [];
            foreach (['fields', 'is_async', 'soft_fail'] as $name) {
                if (\array_key_exists($name, $remaining)) {
                    $globals[] = [$name, self::toWireString($name, $remaining[$name])];
                    unset($remaining[$name]);
                }
            }

            return [$path, $globals, $remaining];
        }
        $pairs = [];
        foreach ($remaining as $name => $value) {
            if (\is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    if ($item !== null) {
                        $pairs[] = [$name, self::toWireString($name, $item)];
                    }
                }
            } else {
                $pairs[] = [$name, self::toWireString($name, $value)];
            }
        }

        return [$path, $pairs, null];
    }

    public static function toWireString(string $name, mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            if (!is_finite($value)) {
                throw new SerializationException(sprintf('The "%s" parameter must be a finite number.', $name), $name);
            }
            if (floor($value) === $value && abs($value) < 1e15) {
                return (string) (int) $value;
            }

            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        if (\is_array($value)) {
            return self::encodeJson($value);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new SerializationException(sprintf('The "%s" parameter has an unsupported type (%s).', $name, get_debug_type($value)), $name);
    }

    /**
     * @param list<array{0: string, 1: string}> $pairs
     */
    public static function encodeQuery(array $pairs): string
    {
        return implode('&', array_map(static fn(array $pair): string => rawurlencode($pair[0]) . '=' . rawurlencode($pair[1]), $pairs));
    }

    /**
     * @param array<mixed> $value
     */
    public static function encodeJson(array $value): string
    {
        try {
            return json_encode($value === [] ? new \stdClass() : $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $exception) {
            throw new SerializationException('The request body could not be serialized as JSON.', null, $exception);
        }
    }
}
