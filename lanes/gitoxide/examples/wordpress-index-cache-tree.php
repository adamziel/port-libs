<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\IndexCacheTree;
use PortLibs\Gitoxide\IndexEntry;
use PortLibs\Gitoxide\IndexFile;
use PortLibs\Gitoxide\SparseCheckoutSpec;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$objects = [];
$write = static function (GitObject $object) use (&$objects): string {
    $oid = $object->oid();
    $objects[$oid] = $object;

    return $oid;
};
$read = static function (string $oid) use (&$objects): GitObject {
    if (!isset($objects[$oid])) {
        throw new RuntimeException("Object not found: {$oid}");
    }

    return $objects[$oid];
};
$blob = static fn (string $filename, string $content, string $mode = '100644'): TreeEntry => new TreeEntry(
    $mode,
    $filename,
    $write(new GitObject('blob', $content)),
);
$tree = static fn (string $filename, Tree $tree): TreeEntry => new TreeEntry(
    '40000',
    $filename,
    $write($tree->toObject()),
);

$root = new Tree([
    $blob('index.php', "<?php\n"),
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $blob('plugin-loader.php', "<?php\n"),
            $tree('akismet', new Tree([
                $blob('akismet.php', "<?php\n"),
            ])),
            $tree('gutenberg', new Tree([
                $blob('block.json', "{\"name\":\"core/demo\"}\n"),
                $tree('src', new Tree([
                    $blob('editor.js', "export default true;\n", '100755'),
                ])),
            ])),
        ])),
        $tree('uploads', new Tree([
            $tree('2026', new Tree([
                $blob('hero.jpg', "jpeg-bytes\n"),
            ])),
        ])),
    ])),
]);

$sparse = SparseCheckoutSpec::cone(['wp-content/plugins/gutenberg']);
$indexBytes = IndexFile::bytesForCheckout($root, $read, $sparse);
$entries = IndexFile::entriesFromBytes($indexBytes);
$cacheTree = IndexFile::cacheTreeFromBytes($indexBytes);
$cacheTree?->verifyEntryCounts(count($entries));
$verifiedCacheTree = IndexFile::verifyCheckoutCacheTree($indexBytes, $root, $read, $sparse);

return [
    'indexVersion' => IndexFile::versionFromBytes($indexBytes),
    'entryCount' => count($entries),
    'skippedPaths' => array_values(array_map(
        static fn (IndexEntry $entry): string => $entry->path,
        array_filter($entries, static fn (IndexEntry $entry): bool => $entry->skipWorktree),
    )),
    'cacheTree' => [
        'signature' => IndexCacheTree::SIGNATURE,
        'rootOid' => $cacheTree?->oid,
        'rootEntries' => $cacheTree?->numEntries,
        'wpContentEntries' => $cacheTree?->childNamed('wp-content')?->numEntries,
        'pluginEntries' => $cacheTree?->childNamed('wp-content')?->childNamed('plugins')?->numEntries,
        'checkoutParityVerified' => $verifiedCacheTree->oid === $root->toObject()->oid(),
    ],
    'checksum' => IndexFile::checksum($indexBytes),
];
