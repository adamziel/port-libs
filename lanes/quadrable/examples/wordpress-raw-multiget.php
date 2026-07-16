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

$queryKeys = [
    Key::fromInteger(1),
    Key::fromInteger(3),
    Key::fromInteger(404),
];
$query = $tree->getMultiRaw($queryKeys);

$recordsById = [];
foreach ($queryKeys as $key) {
    $recordsById[(string) $key->toInteger()] = $query[$key->hex()];
}

echo json_encode([
    'scenario' => 'batch read raw integer WordPress snapshot keys without rehashing ids',
    'root' => $tree->rootHash(),
    'recordsById' => $recordsById,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
