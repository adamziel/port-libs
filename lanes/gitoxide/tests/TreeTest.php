<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\IndexEntry;
use PortLibs\Gitoxide\MergeIndexEntry;
use PortLibs\Gitoxide\MergeWorktreeFile;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$entry = static function (string $mode, string $filename, string $oid): string {
    $oidBytes = hex2bin($oid);
    if ($oidBytes === false) {
        throw new RuntimeException('Invalid fixture oid');
    }

    return $mode . ' ' . $filename . "\0" . $oidBytes;
};

$everything = $entry('100755', 'exe', 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391')
    . $entry('100644', 'file', 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391')
    . $entry('160000', 'grit-submodule', 'b2d1b5d684bdfda5f922b466cc13d4ce2d635cf8')
    . $entry('40000', 'subdir', '4d5fcadc293a348e88f777dc0920f11e7d71441c')
    . $entry('120000', 'symlink', '1a010b1c0f081b2e8901d55307a15c29ff30af0e');

return [
    'empty tree body is valid' => static function (TestRunner $t): void {
        $tree = Tree::parse('');
        $t->same([], $tree->entries);
        $t->same('', $tree->storageBytes());
    },
    'parses upstream everything tree entry kinds and oids' => static function (TestRunner $t) use ($everything): void {
        $tree = Tree::parse($everything);
        $t->same(5, count($tree->entries));
        $t->same('blob-executable', $tree->entries[0]->kind());
        $t->same('exe', $tree->entries[0]->filename);
        $t->same('blob', $tree->entries[1]->kind());
        $t->same('commit', $tree->entries[2]->kind());
        $t->same('tree', $tree->entries[3]->kind());
        $t->same('link', $tree->entries[4]->kind());
        $t->same('1a010b1c0f081b2e8901d55307a15c29ff30af0e', $tree->entries[4]->oid);
    },
    'tree round trips through a native Git object' => static function (TestRunner $t) use ($everything): void {
        $tree = Tree::parse($everything);
        $object = $tree->toObject();
        $roundTrip = Tree::fromObject(GitObject::fromStorageBytes($object->storageBytes()));
        $t->same($everything, $roundTrip->storageBytes());
        $t->same($object->oid(), $roundTrip->toObject()->oid());
    },
    'tree parser accepts empty entry modes as gix-object mode zero' => static function (TestRunner $t) use ($entry): void {
        $oid = '4d5fcadc293a348e88f777dc0920f11e7d71441c';
        $body = $entry('', 'block.html', $oid);
        $tree = Tree::parse($body);

        $t->same(1, count($tree->entries));
        $t->same('', $tree->entries[0]->mode);
        $t->same('block.html', $tree->entries[0]->filename);
        $t->same($oid, $tree->entries[0]->oid);
        $t->same('commit', $tree->entries[0]->kind());
        $t->same($body, $tree->storageBytes());
    },
    'empty tree mode exception does not apply to index and merge entries' => static function (TestRunner $t): void {
        $oid = str_repeat('0', 40);

        $t->throws(InvalidArgumentException::class, static fn () => new IndexEntry('file.php', IndexEntry::STAGE_NORMAL, '', $oid));
        $t->throws(InvalidArgumentException::class, static fn () => new MergeIndexEntry('file.php', MergeIndexEntry::STAGE_OURS, '', $oid));
        $t->throws(InvalidArgumentException::class, static fn () => new MergeWorktreeFile('file.php', '', $oid, ''));
    },
    'entry mode helpers follow gix-object kind semantics' => static function (TestRunner $t): void {
        $t->same('commit', (new TreeEntry('', 'empty-mode', str_repeat('0', 40)))->kind());
        $t->same('blob', (new TreeEntry('100645', 'file', str_repeat('0', 40)))->kind());
        $t->same('blob-executable', (new TreeEntry('100700', 'file', str_repeat('0', 40)))->kind());
        $t->same('link', (new TreeEntry('121234', 'link', str_repeat('0', 40)))->kind());
        $t->same('tree', (new TreeEntry('040101', 'dir', str_repeat('0', 40)))->kind());
        $t->same('tree', (new TreeEntry('40101', 'dir', str_repeat('0', 40)))->kind());
        $t->same('commit', (new TreeEntry('167124', 'submodule', str_repeat('0', 40)))->kind());
        $t->same('commit', (new TreeEntry('000000', 'unknown', str_repeat('0', 40)))->kind());
    },
    'leading spaces in filenames are preserved' => static function (TestRunner $t) use ($entry): void {
        $oid = '4d5fcadc293a348e88f777dc0920f11e7d71441c';
        $tree = Tree::parse($entry('40000', ' leading space', $oid));
        $t->same(' leading space', $tree->entries[0]->filename);
        $t->same($oid, $tree->entryNamed(' leading space', true)?->oid);
    },
    'partial tree object id is rejected' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Tree::parse("100644 file\0short"));
    },
    'malformed tree mode is rejected' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Tree::parse("10099x file\0" . str_repeat("\0", 20)));
    },
    'non-tree git object cannot be parsed as tree' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Tree::fromObject(new GitObject('blob', 'not a tree')));
    },
    'wordpress fixture exposes deployable content tree entries' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-content-tree.php';
        $tree = Tree::parse($fixture['rootTreeBody']);
        $t->same('tree', $tree->entryNamed('wp-content', true)?->kind());
        $t->same('blob', $tree->entryNamed('wp-config.php', false)?->kind());
        $t->same($fixture['expectedRootOid'], $tree->toObject()->oid());
    },
];
