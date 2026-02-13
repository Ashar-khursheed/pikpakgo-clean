<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$username = 'HaXmlSandbox'; // Docs say this is the username for sandbox
$password = '0beefaaa-963c-407c-bd64-bdeadb949417'; // The key
$baseUrl = 'https://faststage.ownerrez.com';

$tests = [
    'Correct Sandbox Auth?' => [
        'url' => "{$baseUrl}/haapi/haxml/advertiserindex?type={$username}",
        'auth' => true
    ]
];

foreach ($tests as $name => $config) {
    echo "\nTesting: $name\n";
    echo "URL: {$config['url']}\n";
    
    try {
        $req = Http::withHeaders([
            'User-Agent' => 'PikPakGo/1.0',
            'Content-Type' => 'application/xml'
        ]);
        
        if ($config['auth']) {
            $req->withBasicAuth($username, $password);
        }
        
        $response = $req->get($config['url']);
        
        echo "Status: " . $response->status() . "\n";
        if ($response->successful()) {
            echo "SUCCESS!\n";
            echo substr($response->body(), 0, 200) . "...\n";
            break; // Stop on first success
        } else {
            echo "Failed.\n";
        }
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
