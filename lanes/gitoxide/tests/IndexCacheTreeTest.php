<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\IndexCacheTree;
use PortLibs\Gitoxide\IndexEntry;
use PortLibs\Gitoxide\IndexFile;
use PortLibs\Gitoxide\SparseCheckoutSpec;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$objectStore = static function (): array {
    $objects = [];
    $write = static function (GitObject $object) use (&$objects): string {
        $oid = $object->oid();
        $objects[$oid] = $object;

        return $oid;
    };
    $read = static function (string $oid) use (&$objects): GitObject {
        if (!isset($objects[$oid])) {
            throw new RuntimeException("Fixture object not found: {$oid}");
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

    return [$read, $blob, $tree];
};

$wordpressCheckoutTree = static function () use ($objectStore): array {
    [$read, $blob, $treeEntry] = $objectStore();
    $root = new Tree([
        $blob('index.php', "<?php\n"),
        $treeEntry('wp-content', new Tree([
            $treeEntry('plugins', new Tree([
                $blob('plugin-loader.php', "<?php\n"),
                $treeEntry('akismet', new Tree([
                    $blob('akismet.php', "<?php\n"),
                ])),
                $treeEntry('gutenberg', new Tree([
                    $blob('block.json', "{\"name\":\"core/demo\"}\n"),
                    $treeEntry('src', new Tree([
                        $blob('editor.js', "export default true;\n", '100755'),
                    ])),
                ])),
            ])),
            $treeEntry('uploads', new Tree([
                $treeEntry('2026', new Tree([
                    $blob('hero.jpg', "jpeg-bytes\n"),
                ])),
            ])),
        ])),
    ]);

    return [$root, $read];
};

$paths = static fn (array $entries): array => array_map(static fn (IndexEntry $entry): string => $entry->path, $entries);
$childNames = static fn (IndexCacheTree $tree): array => array_map(
    static fn (IndexCacheTree $child): string => $child->name,
    $tree->children,
);
$oid = static fn (string $hex): string => str_repeat($hex, 40);
$cacheNode = static function (string $name, int $entries, string $oid, string $children = ''): string {
    $oidBytes = hex2bin($oid);
    if ($oidBytes === false) {
        throw new RuntimeException('Invalid cache-tree fixture oid');
    }
    $childCount = $children === '' ? 0 : substr_count($children, "\0");

    return $name . "\0" . $entries . ' ' . $childCount . "\n" . $oidBytes . $children;
};

return [
    'builds and round trips checkout index TREE cache extension' => static function (TestRunner $t) use ($wordpressCheckoutTree, $paths, $childNames): void {
        [$tree, $read] = $wordpressCheckoutTree();
        $entries = IndexFile::entriesForCheckout($tree, $read);
        $cacheTree = IndexCacheTree::fromTree($tree, $read);
        $bytes = IndexFile::bytesFor($entries, $cacheTree);
        $parsedEntries = IndexFile::entriesFromBytes($bytes);
        $parsedCacheTree = IndexFile::cacheTreeFromBytes($bytes);

        $t->same(2, IndexFile::versionFromBytes($bytes));
        $t->same(6, count($entries));
        $t->same([
            'index.php',
            'wp-content/plugins/akismet/akismet.php',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/src/editor.js',
            'wp-content/plugins/plugin-loader.php',
            'wp-content/uploads/2026/hero.jpg',
        ], $paths($entries));
        $t->same($paths($entries), $paths($parsedEntries));
        $t->same(hash('sha1', substr($bytes, 0, -20)), IndexFile::checksum($bytes));
        $t->same('TREE', substr($cacheTree->extensionBytes(), 0, 4));
        $t->same(6, $cacheTree->numEntries);
        $t->same(6, $parsedCacheTree?->numEntries);
        $t->same(['wp-content'], $childNames($parsedCacheTree));
        $t->same(5, $parsedCacheTree?->childNamed('wp-content')?->numEntries);
        $t->same(4, $parsedCacheTree?->childNamed('wp-content')?->childNamed('plugins')?->numEntries);
        $t->same(2, $parsedCacheTree?->childNamed('wp-content')?->childNamed('plugins')?->childNamed('gutenberg')?->numEntries);
        $t->same(1, $parsedCacheTree?->childNamed('wp-content')?->childNamed('uploads')?->childNamed('2026')?->numEntries);
        $t->same($cacheTree->bodyBytes(), $parsedCacheTree?->bodyBytes());

        $parsedCacheTree?->verifyEntryCounts(count($parsedEntries));
    },
    'sparse checkout index uses v3 skip-worktree flags while cache tree keeps full counts' => static function (TestRunner $t) use ($wordpressCheckoutTree, $paths): void {
        [$tree, $read] = $wordpressCheckoutTree();
        $spec = SparseCheckoutSpec::cone(['wp-content/plugins/gutenberg']);
        $bytes = IndexFile::bytesForCheckout($tree, $read, $spec);
        $entries = IndexFile::entriesFromBytes($bytes);
        $cacheTree = IndexFile::cacheTreeFromBytes($bytes);
        $skipped = array_values(array_map(
            static fn (IndexEntry $entry): string => $entry->path,
            array_filter($entries, static fn (IndexEntry $entry): bool => $entry->skipWorktree),
        ));
        sort($skipped, SORT_STRING);

        $t->same(3, IndexFile::versionFromBytes($bytes));
        $t->same(6, count($entries));
        $t->same([
            'wp-content/plugins/akismet/akismet.php',
            'wp-content/uploads/2026/hero.jpg',
        ], $skipped);
        $t->same(false, $entries[array_search('index.php', $paths($entries), true)]->skipWorktree);
        $t->same(false, $entries[array_search('wp-content/plugins/plugin-loader.php', $paths($entries), true)]->skipWorktree);
        $t->same(false, $entries[array_search('wp-content/plugins/gutenberg/src/editor.js', $paths($entries), true)]->skipWorktree);
        $t->same(true, $entries[array_search('wp-content/plugins/akismet/akismet.php', $paths($entries), true)]->skipWorktree);
        $t->same(6, $cacheTree?->numEntries);
        $t->same(5, $cacheTree?->childNamed('wp-content')?->numEntries);

        $cacheTree?->verifyEntryCounts(count($entries));
    },
    'cache tree decoder sorts children and rejects duplicate or trailing data' => static function (TestRunner $t) use ($oid, $cacheNode, $childNames): void {
        $body = $cacheNode('', 0, $oid('1'), $cacheNode('z', 0, $oid('2')) . $cacheNode('a', 0, $oid('3')));
        $tree = IndexCacheTree::fromBody($body);

        $t->same(['a', 'z'], $childNames($tree));
        $t->same($cacheNode('', 0, $oid('1'), $cacheNode('a', 0, $oid('3')) . $cacheNode('z', 0, $oid('2'))), $tree->bodyBytes());
        $t->throws(InvalidArgumentException::class, static fn () => IndexCacheTree::fromBody($body . 'trailing'));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => IndexCacheTree::fromBody($cacheNode('', 0, $oid('1'), $cacheNode('a', 0, $oid('2')) . $cacheNode('a', 0, $oid('3')))),
        );
    },
    'cache tree verification catches impossible index entry counts' => static function (TestRunner $t) use ($oid): void {
        $tooLarge = new IndexCacheTree('', $oid('4'), 4);
        $badRoot = new IndexCacheTree('not-root', $oid('5'), 0);

        $t->throws(RuntimeException::class, static fn () => $tooLarge->verifyEntryCounts(3));
        $t->throws(RuntimeException::class, static fn () => $badRoot->verifyEntryCounts(0));
    },
    'index parser rejects corrupt checksum and malformed extensions' => static function (TestRunner $t) use ($oid): void {
        $entry = new IndexEntry('index.php', IndexEntry::STAGE_NORMAL, '100644', $oid('6'));
        $bytes = IndexFile::bytesFor([$entry], new IndexCacheTree('', $oid('7'), 1));
        $badChecksum = substr($bytes, 0, -1) . (substr($bytes, -1) === "\0" ? "\1" : "\0");
        $badExtension = substr($bytes, 0, -20);
        $treeOffset = strpos($badExtension, 'TREE');
        if ($treeOffset === false) {
            throw new RuntimeException('TREE fixture extension not found');
        }
        $badExtension = substr($badExtension, 0, $treeOffset + 4) . pack('N', 1000) . substr($badExtension, $treeOffset + 8);
        $badExtension .= hex2bin(hash('sha1', $badExtension));

        $t->throws(RuntimeException::class, static fn () => IndexFile::entriesFromBytes($badChecksum));
        $t->throws(InvalidArgumentException::class, static fn () => IndexFile::cacheTreeFromBytes($badExtension));
    },
];
