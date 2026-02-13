<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$username = 'HaXmlSandboxMoR';
$password = '0beefaaa-963c-407c-bd64-bdeadb949417';
$ids = [
    'PikPakGo/1.0',
    'OwnerRez-Channel-Test/1.0',
    $username // Trying username as UA
];

$urls = [
    'https://api.ownerrez.com/v2/properties',
    'https://api.ownerrez.com/ch/properties', // Guessing 'ch' for channel
    'https://api.ownerrez.com/oauth/token',
];

foreach ($urls as $url) {
    echo "\nTesting URL: $url\n";
    foreach ($ids as $ua) {
        echo "  UA: $ua -> ";
        try {
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders(['User-Agent' => $ua])
                ->timeout(5)
                ->get($url);
                
            echo $response->status();
            if ($response->successful()) {
                echo " SUCCESS!\n";
                // echo substr($response->body(), 0, 100) . "...\n";
            } else {
                echo " " . ($response->json()['message'] ?? 'Failed') . "\n";
            }
        } catch (\Exception $e) {
            echo " Error: " . $e->getMessage() . "\n";
        }
    }
}
