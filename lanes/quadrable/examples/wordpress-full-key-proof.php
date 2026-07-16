<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tree = new SparseTree();
$tree->change()
    ->put('wp_options:siteurl', 'https://example.test')
    ->put('wp_options:home', 'https://example.test')
    ->put('wp_posts:1', 'Hello world')
    ->apply();

$trustedRoot = $tree->rootHash();
$encodedProof = $tree->exportProof([
    'wp_options:siteurl',
    'wp_posts:1',
    'wp_posts:404',
])->encode(Proof::ENCODING_FULL_KEYS);

$partial = SparseTree::importProof(Proof::decode($encodedProof), $trustedRoot);
$entries = [];
foreach ($partial->orderedEntries() as $entry) {
    $entries[] = [
        'key' => $entry->stringKey(),
        'value' => $entry->value(),
    ];
}

$homeAvailable = true;
try {
    $partial->get('wp_options:home');
} catch (RuntimeException) {
    $homeAvailable = false;
}

echo json_encode([
    'scenario' => 'authenticate WordPress records with FullKeys proof transport and enumerate proven keys',
    'trustedRoot' => $trustedRoot,
    'encodingType' => Proof::ENCODING_FULL_KEYS,
    'encodedProofBytes' => strlen($encodedProof),
    'siteurl' => $partial->get('wp_options:siteurl'),
    'missingPostExists' => $partial->get('wp_posts:404') !== null,
    'unprovenHomeAvailable' => $homeAvailable,
    'enumeratedRecords' => $entries,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
