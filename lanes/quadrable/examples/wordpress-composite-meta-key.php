<?php

declare(strict_types=1);

use PortLibs\Quadrable\Blake2s;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$metaKey = static fn (int $postId, string $metaName): Key => Key::fromIntegerAndHash(
    $postId,
    substr(Blake2s::hash($metaName), -23)
);

$tree = new SparseTree();
$tree->change()
    ->putKey($metaKey(42, '_thumbnail_id'), 'wp_postmeta:42:_thumbnail_id=7')
    ->putKey($metaKey(42, '_edit_lock'), 'wp_postmeta:42:_edit_lock=1716400000')
    ->putKey($metaKey(42, '_wp_page_template'), 'wp_postmeta:42:_wp_page_template=templates/full-width.html')
    ->putKey($metaKey(43, '_thumbnail_id'), 'wp_postmeta:43:_thumbnail_id=8')
    ->apply();

$postPrefix = substr(Key::fromInteger(42)->bytes(), 0, 9);
$rowsForPost = [];
$iterator = $tree->iterate(Key::fromIntegerAndHash(42, str_repeat("\0", 23)));

while (!$iterator->atEnd()) {
    $entry = $iterator->get();
    if ($entry === null || substr($entry->key()->bytes(), 0, 9) !== $postPrefix) {
        break;
    }

    $rowsForPost[] = [
        'keyHex' => $entry->keyHex(),
        'value' => $entry->value(),
    ];
    $iterator->next();
}

echo json_encode([
    'root' => $tree->rootHash(),
    'postId' => 42,
    'rowsForPost' => $rowsForPost,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
