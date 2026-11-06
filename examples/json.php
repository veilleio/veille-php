<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Veille\Client;

$client = new Client(); // reads VEILLE_API_KEY
$response = $client->intelligenceEmail(['query' => 'jane.doe@example.com']);
echo json_encode($response->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
