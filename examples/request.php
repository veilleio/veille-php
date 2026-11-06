<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Veille\Client;

$client = new Client(); // reads VEILLE_API_KEY
try {
    $response = $client->request('GET', '/v1/usage');
    if ($response instanceof Veille\JsonResponse) {
        print_r($response->data);
    }
} catch (Veille\Exception\ApiException $exception) {
    fwrite(STDERR, sprintf("HTTP %d: %s\n", $exception->status, $exception->getMessage()));
}
