<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\MergeIndexEntry;
use PortLibs\Gitoxide\MergeIndexFile;
use PortLibs\Gitoxide\MergeWorktreeWriter;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$blob = static fn (string $name, string $content): TreeEntry => new TreeEntry('100644', $name, $write(new GitObject('blob', $content)));
$tree = static fn (string $name, Tree $tree): TreeEntry => new TreeEntry('40000', $name, $write($tree->toObject()));
$wpContent = static fn (TreeEntry $metadata, TreeEntry $theme): Tree => new Tree([
    $metadata,
    $tree('themes', new Tree([
        $tree('acme', new Tree([$theme])),
    ])),
]);

$base = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"base\"\n}\n"),
    )),
]);
$ours = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo Import\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"blue\"\n}\n"),
    )),
]);
$theirs = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: publish\n"),
        $blob('theme.json', "{\n  \"color\": \"green\"\n}\n"),
    )),
]);

$result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
$virtualAncestor = new Tree([
    $blob('wp-plugin.php', "Plugin Name: Acme\nVersion: 1.0\n"),
]);
$virtualFirstBase = new Tree([
    $blob('wp-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.0\n"),
]);
$virtualSecondBase = new Tree([
    $blob('wp-plugin.php', "Plugin Name: Acme\nVersion: 1.0\nText Domain: acme\n"),
]);
$virtualOurs = new Tree([
    $blob('wp-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.1\n"),
]);
$virtualTheirs = new Tree([
    $blob('acme-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.0\nText Domain: acme-plugin\n"),
]);
$virtualResult = TreeMerge::mergeRecursiveWithVirtualBase(
    $virtualAncestor,
    [$virtualFirstBase, $virtualSecondBase],
    $virtualOurs,
    $virtualTheirs,
    $read,
    $write,
);
$virtualIndexEntries = $virtualResult->indexEntries();
$contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
$metadata = $read($contentTree->entryNamed('post.meta')?->oid ?? '');
$demoRoot = sys_get_temp_dir() . '/port-libs-recursive-merge-' . bin2hex(random_bytes(4));
$indexChecksum = MergeIndexFile::writeResult($demoRoot . '/.git/index', $result, $read);
$worktreeRoot = $demoRoot . '/worktree';
mkdir($worktreeRoot . '/.git', 0777, true);
mkdir($worktreeRoot . '/wp-content/plugins/old-plugin', 0777, true);
file_put_contents($worktreeRoot . '/.git/config', "[core]\n");
file_put_contents($worktreeRoot . '/wp-content/plugins/old-plugin/bootstrap.php', "<?php\nreturn 'stale';\n");
$worktreeFiles = MergeWorktreeWriter::checkoutMergedTree($result, $worktreeRoot, $read);
$themeJsonPath = $worktreeRoot . '/wp-content/themes/acme/theme.json';

echo json_encode([
    'clean' => $result->isClean(),
    'conflicts' => array_map(
        static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
        $result->conflicts,
    ),
    'indexStages' => array_map(
        static fn (MergeIndexEntry $entry): array => [
            'path' => $entry->path,
            'stage' => $entry->stage,
            'side' => $entry->side(),
            'oid' => $entry->oid,
        ],
        $result->indexEntries(),
    ),
    'worktreeConflictFiles' => array_map(
        static fn ($file): array => [
            'path' => $file->path,
            'oid' => $file->oid,
            'containsMarkers' => str_contains($file->content, '<<<<<<<'),
        ],
        $result->worktreeConflictFiles($read),
    ),
    'writtenIndex' => [
        'path' => $demoRoot . '/.git/index',
        'checksum' => $indexChecksum,
        'stages' => count(MergeIndexFile::entriesFromBytes((string) file_get_contents($demoRoot . '/.git/index'))),
    ],
    'writtenWorktree' => [
        'root' => $worktreeRoot,
        'files' => array_map(static fn ($file): string => $file->path, $worktreeFiles),
        'themeJsonContainsMarkers' => str_contains((string) file_get_contents($themeJsonPath), '<<<<<<<'),
        'stalePluginRemoved' => !file_exists($worktreeRoot . '/wp-content/plugins/old-plugin/bootstrap.php'),
        'gitMetadataPreserved' => is_file($worktreeRoot . '/.git/config'),
    ],
    'mergedMetadata' => $metadata->body,
    'virtualMergeBase' => [
        'clean' => $virtualResult->isClean(),
        'entries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $virtualResult->tree->entries),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $virtualResult->conflicts,
        ),
        'ancestorStageContainsBothBaseEdits' => isset($virtualIndexEntries[0])
            && str_contains($read($virtualIndexEntries[0]->oid)->body, 'Requires PHP: 8.1')
            && str_contains($read($virtualIndexEntries[0]->oid)->body, 'Text Domain: acme'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
