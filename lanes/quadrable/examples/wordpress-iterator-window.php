<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$tree = new SparseTree();
$changes = $tree->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$window = [];
$iterator = $tree->iterate(Key::fromInteger(2));

while (!$iterator->atEnd() && count($window) < 3) {
    $entry = $iterator->get();
    if ($entry === null) {
        break;
    }

    $window[] = [
        'key' => $entry->key()->toInteger(),
        'value' => $entry->value(),
    ];
    $iterator->next();
}

echo json_encode([
    'root' => $tree->rootHash(),
    'window' => $window,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
