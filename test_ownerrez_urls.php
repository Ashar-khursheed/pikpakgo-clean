<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$username = 'HaXmlSandboxMoR';
$password = '0beefaaa-963c-407c-bd64-bdeadb949417';

$urls = [
    'https://faststage.ownerrez.com/properties',
    'https://faststage.ownerrez.com/api/properties',
    'https://api.ownerrez.com/v2/properties',
    'https://faststage.ownerrez.com/v2/properties'
];

foreach ($urls as $url) {
    echo "Testing URL: $url\n";
    try {
        $response = Http::withBasicAuth($username, $password)
            ->timeout(5)
            ->get($url, ['limit' => 1]);
            
        echo "Status: " . $response->status() . "\n";
        if ($response->successful()) {
            echo "SUCCESS!\n";
            echo substr($response->body(), 0, 200) . "...\n";
            break;
        } else {
            echo "Failed.\n";
        }
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "-------------------\n";
}
