<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$username = 'HaXmlSandboxMoR';
$password = '0beefaaa-963c-407c-bd64-bdeadb949417';

$tests = [
    'Listing Index (HaXmlSandboxMoR ID)' => [
        'url' => 'https://faststage.ownerrez.com/haapi/haxml/HaXmlSandboxMoR/listingindex',
        'method' => 'GET'
    ],
    'Listing Index (HaXmlSandbox ID)' => [
        'url' => 'https://faststage.ownerrez.com/haapi/haxml/HaXmlSandbox/listingindex',
        'method' => 'GET'
    ]
];

foreach ($tests as $name => $config) {
    echo "\nTesting: $name\n";
    echo "URL: {$config['url']}\n";
    
    try {
        $req = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'User-Agent' => 'PikPakGo/1.0',
                'Content-Type' => $config['method'] === 'POST' ? 'application/json' : 'application/xml'
            ]);
            
        if ($config['method'] === 'GET') {
            $response = $req->get($config['url']);
        } else {
            $response = $req->post($config['url'], $config['body'] ?? []);
        }
        
        echo "Status: " . $response->status() . "\n";
        if ($response->successful() || $response->status() === 400) {
            echo "SUCCESS/EXPECTED!\n";
            echo substr($response->body(), 0, 300) . "...\n";
        } else {
            echo "Failed.\n";
            echo substr($response->body(), 0, 100) . "...\n";
        }
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
