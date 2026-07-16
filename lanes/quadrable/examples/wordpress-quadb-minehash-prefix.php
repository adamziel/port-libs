<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefix = '101010';
$first = Key::mineHashPrefix($prefix, 1, 200);
$second = Key::mineHashPrefix($prefix, ((int) $first['input']) + 1, 50);

$tree = new SparseTree();
$tree->change()
    ->put($first['input'], 'wp_posts:prefix-fixture:' . $first['input'])
    ->put($second['input'], 'wp_posts:prefix-fixture:' . $second['input'])
    ->apply();

$proofBytes = $tree->exportProof([$first['input'], $second['input']])->encode();

echo json_encode([
    'scenario' => 'deterministic quadb mineHash prefix fixtures for WordPress proof-depth stress',
    'prefix' => $prefix,
    'commandOutput' => QuadbStore::mineHashCommandOutput($prefix, 1, 200),
    'invalidPrefixCommandOutput' => QuadbStore::mineHashCommandOutput('10x', 1, 200),
    'candidates' => [$first, $second],
    'allCandidatesMatchPrefix' => Key::hashMatchesBitPrefix($first['input'], $prefix)
        && Key::hashMatchesBitPrefix($second['input'], $prefix),
    'root' => $tree->rootHash(),
    'proofBytes' => strlen($proofBytes),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
