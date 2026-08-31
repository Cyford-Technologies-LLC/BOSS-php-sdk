<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Exceptions/SdkException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/Exceptions/AuthException.php';
require __DIR__ . '/../src/Exceptions/RateLimitException.php';
require __DIR__ . '/../src/Exceptions/ApiException.php';
require __DIR__ . '/../src/Http/HttpClientInterface.php';
require __DIR__ . '/../src/Http/CurlHttpClient.php';
require __DIR__ . '/../src/Auth/RequestSigner.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/ResourceRecord.php';
require __DIR__ . '/../src/Resources/AbstractResource.php';
require __DIR__ . '/../src/Resources/CreatesRecords.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$boss = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => getenv('BOSS_TEST_BASE_URL') ?: 'http://localhost/api/v2',
]);

echo "--- import: opencart schema ---\n";
$r1 = $boss->call('POST', '/inventory/products/import', [], [
    'schema' => 'opencart',
    'products' => [
        [
            'product_id' => 501,
            'model' => 'OC-WIDGET-1',
            'sku' => 'OC-WIDGET-1',
            'name' => 'OpenCart Widget',
            'description' => 'Imported from OpenCart',
            'price' => '19.9900',
            'quantity' => 42,
            'weight' => '1.5000',
            'weight_class_id' => 1,
            'subtract' => 1,
            'status' => 1,
            'images' => ['catalog/widget1.jpg'],
            'category_ids' => [20, 21],
            'category_name' => 'Widgets',
        ],
    ],
]);
echo json_encode($r1) . "\n";

echo "\n--- import: woocommerce schema ---\n";
$r2 = $boss->call('POST', '/inventory/products/import', [], [
    'schema' => 'woocommerce',
    'products' => [
        [
            'id' => 900,
            'sku' => 'WC-GADGET-1',
            'name' => 'WooCommerce Gadget',
            'description' => 'Imported from WooCommerce',
            'regular_price' => '29.99',
            'sale_price' => '24.99',
            'stock_quantity' => 7,
            'manage_stock' => true,
            'backorders' => 'no',
            'weight' => '0.8',
            'status' => 'publish',
            'categories' => [['name' => 'Gadgets']],
            'images' => [['src' => 'https://example.com/gadget.jpg']],
        ],
    ],
]);
echo json_encode($r2) . "\n";

echo "\n--- import: canonical schema ---\n";
$r3 = $boss->call('POST', '/inventory/products/import', [], [
    'schema' => 'canonical',
    'products' => [
        ['sku' => 'CANON-1', 'name' => 'Canonical Direct', 'price' => 5.5, 'stock_qty' => 3],
    ],
]);
echo json_encode($r3) . "\n";

echo "\n--- re-import opencart record (same opencart_id) - expect updated, not created ---\n";
$r4 = $boss->call('POST', '/inventory/products/import', [], [
    'schema' => 'opencart',
    'products' => [
        ['product_id' => 501, 'model' => 'OC-WIDGET-1', 'sku' => 'OC-WIDGET-1', 'name' => 'OpenCart Widget v2', 'price' => '21.99', 'quantity' => 50, 'status' => 1],
    ],
]);
echo json_encode($r4) . "\n";

echo "\n--- bad record mixed with good record - expect partial success ---\n";
$r5 = $boss->call('POST', '/inventory/products/import', [], [
    'schema' => 'canonical',
    'products' => [
        ['sku' => 'OK-1', 'name' => 'Good record', 'price' => 1],
        'not-an-object',
    ],
]);
echo json_encode($r5) . "\n";

echo "\n--- verify via list ---\n";
$list = $boss->call('GET', '/inventory/products', ['search' => 'Widget']);
foreach (($list['products'] ?? []) as $p) {
    echo $p['sku'] . ' | ' . $p['name'] . ' | price=' . $p['price'] . ' | qty=' . $p['stock_qty'] . ' | category_id=' . $p['category_id'] . ' | opencart_id=' . $p['opencart_id'] . "\n";
}
