<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$creds = [
    ['user' => 'HaXmlSandbox', 'pass' => '0beefaaa-963c-407c-bd64-bdeadb949417'],
    ['user' => 'HaXmlSandboxMoR', 'pass' => '0beefaaa-963c-407c-bd64-bdeadb949417'],
];

$urls = [
    'https://faststage.ownerrez.com/haapi/haolbjson/fastavailability',
    'https://fast.ownerrez.com/haapi/haolbjson/fastavailability',
];

foreach ($urls as $url) {
    echo "\nTesting URL: $url\n";
    foreach ($creds as $cred) {
        echo "  User: {$cred['user']} -> ";
        try {
            $response = Http::withBasicAuth($cred['user'], $cred['pass'])
                ->withHeaders([
                    'User-Agent' => 'PikPakGo/1.0',
                    'Content-Type' => 'application/json'
                ])
                ->post($url, ['requestVersion' => '1.0']); // Minimal body
                
            echo $response->status();
            if ($response->successful()) {
                echo " SUCCESS!\n";
            } else {
                echo " " . ($response->json()['message'] ?? 'Failed') . "\n";
                // echo substr($response->body(), 0, 100) . "...\n";
            }
        } catch (\Exception $e) {
            echo " Error: " . $e->getMessage() . "\n";
        }
    }
}
