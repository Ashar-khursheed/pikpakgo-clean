<?php

use App\Services\OwnerRezService;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Env URL: " . env('OWNERREZ_BASE_URL') . "\n";
echo "Config URL: " . config('services.ownerrez.base_url') . "\n";

$service = new OwnerRezService();
$result = $service->searchProperties(['guests' => 2]);

echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
if (!$result['success']) {
    echo "Message: " . $result['message'] . "\n";
    echo "URL: " . ($result['url'] ?? 'N/A') . "\n";
    print_r($result['error']);
} else {
    echo "Found " . count($result['data']['items'] ?? $result['data'] ?? []) . " properties.\n";
}
