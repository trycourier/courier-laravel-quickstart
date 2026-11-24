<?php

require __DIR__.'/vendor/autoload.php';

$apiKey = getenv('COURIER_API_KEY') ?: throw new RuntimeException('Set COURIER_API_KEY');
$email = getenv('DEMO_EMAIL') ?: throw new RuntimeException('Set DEMO_EMAIL');

$response = (new Courier\Client(apiKey: $apiKey))->send->message(message: [
    'to' => ['email' => $email],
    'content' => ['title' => 'Hello from Laravel', 'body' => 'Your first Courier notification.'],
    'routing' => ['method' => 'single', 'channels' => ['email']],
]);

echo "Sent. Request id: {$response->requestID}\n";
