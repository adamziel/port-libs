<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream block equality shortcuts and fallback' => static function (TestRunner $t): void {
        $blockOne = hash('sha256', 'block-one');
        $blockTwo = hash('sha256', 'block-two');
        $hashOne = hash('sha256', 'hash-one');
        $hashTwo = hash('sha256', 'hash-two');

        $a = new FileInfo(blocks: [new Block(0, 4, $blockOne)], blocksHash: $hashOne);
        $b = new FileInfo(blocks: [new Block(0, 4, $blockTwo)], blocksHash: $hashOne);
        $t->true($a->blocksEqual($b), 'matching aggregate block hashes win without inspecting blocks');

        $sameBlocks = new FileInfo(blocks: [new Block(99, 123, $blockOne)], blocksHash: $hashTwo);
        $t->true($a->blocksEqual($sameBlocks), 'fallback compares block hashes and ignores offsets/sizes like upstream');

        $differentBlocks = new FileInfo(blocks: [new Block(0, 4, $blockTwo)], blocksHash: $hashTwo);
        $t->true(!$a->blocksEqual($differentBlocks));
        $t->true(!$a->blocksEqual(new FileInfo()));
        $t->true((new FileInfo(blocksHash: $hashOne))->blocksEqual(new FileInfo()));
    },
    'maps upstream file info equivalence basic attributes' => static function (TestRunner $t): void {
        $block = new Block(0, 7, hash('sha256', 'content'));
        $blocksHash = hash('sha256', hex2bin($block->hashHex));
        $make = static function (array $overrides = []) use ($block, $blocksHash): FileInfo {
            return new FileInfo(
                name: $overrides['name'] ?? 'wp-content/uploads/hero.jpg',
                modifiedS: $overrides['modifiedS'] ?? 1700000000,
                modifiedNs: $overrides['modifiedNs'] ?? 15,
                version: $overrides['version'] ?? new VersionVector(),
                deleted: $overrides['deleted'] ?? false,
                localFlags: $overrides['localFlags'] ?? 0,
                size: $overrides['size'] ?? 7,
                blocksHash: $overrides['blocksHash'] ?? $blocksHash,
                type: $overrides['type'] ?? FileInfo::TYPE_FILE,
                permissions: $overrides['permissions'] ?? 0644,
                noPermissions: $overrides['noPermissions'] ?? false,
                rawBlockSize: $overrides['rawBlockSize'] ?? 128 << 10,
                sequence: $overrides['sequence'] ?? 1,
                blocks: $overrides['blocks'] ?? [$block],
            );
        };

        $base = $make();
        $t->true($base->isEquivalent($make()));
        $t->true(!$base->isEquivalent($make(['name' => 'wp-content/uploads/other.jpg'])));
        $t->true(!$base->isEquivalent($make(['type' => FileInfo::TYPE_DIRECTORY])));
        $t->true(!$base->isEquivalent($make(['size' => 8])));
        $t->true(!$base->isEquivalent($make(['deleted' => true])));
        $t->true(!$base->isEquivalent($make(['modifiedS' => 1700000001])));
        $t->true(!$base->isEquivalent($make(['modifiedNs' => 16])));
        $t->true($base->isEquivalent($make([
            'version' => VersionVector::fromCounters([42 => 99]),
            'sequence' => 2,
            'rawBlockSize' => 256 << 10,
            'noPermissions' => true,
        ])));
        $t->true($base->isEquivalent($make(['modifiedNs' => 16]), new FileInfoComparison(modTimeWindowNs: 2)));
    },
    'maps upstream local flag equivalence handling' => static function (TestRunner $t): void {
        $clean = new FileInfo();
        $remoteInvalid = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_REMOTE_INVALID);
        $unsupported = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_UNSUPPORTED);
        $receiveOnly = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY);
        $mustRescan = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_MUST_RESCAN);

        $t->true(!$clean->isIgnored());
        $t->true(!$clean->mustRescan());
        $t->true(!$clean->isInvalid());

        $t->true($remoteInvalid->isEquivalent(new FileInfo(localFlags: FileInfo::FLAG_LOCAL_REMOTE_INVALID)));
        $t->true($remoteInvalid->isEquivalent($unsupported), 'invalidity, not exact local flag reason, drives equivalence');
        $t->true(!$clean->isEquivalent($receiveOnly));
        $t->true($clean->isEquivalent($receiveOnly, new FileInfoComparison(ignoreFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY)));
        $t->true(!$mustRescan->isEquivalent(new FileInfo(localFlags: FileInfo::FLAG_LOCAL_MUST_RESCAN)));
    },
    'maps upstream permission and block ignore options' => static function (TestRunner $t): void {
        $blockOne = new Block(0, 4, hash('sha256', 'one'));
        $blockTwo = new Block(0, 4, hash('sha256', 'two'));
        $a = new FileInfo(size: 4, blocks: [$blockOne], permissions: 0444);
        $b = new FileInfo(size: 4, blocks: [$blockOne], permissions: 0666);
        $changedBlocks = new FileInfo(size: 4, blocks: [$blockTwo], permissions: 0444);

        $t->true(!$a->isEquivalent($b));
        $t->true($a->isEquivalent($b, new FileInfoComparison(ignorePerms: true)));
        $t->true($a->isEquivalent(new FileInfo(size: 4, blocks: [$blockOne], permissions: 0600, noPermissions: true)));
        $t->true(!$a->isEquivalent($changedBlocks));
        $t->true($a->isEquivalent($changedBlocks, new FileInfoComparison(ignoreBlocks: true)));
    },
    'maps symlink target and unix ownership equivalence' => static function (TestRunner $t): void {
        $linkA = new FileInfo(type: FileInfo::TYPE_SYMLINK, symlinkTarget: 'uploads/current');
        $linkB = new FileInfo(type: FileInfo::TYPE_SYMLINK, symlinkTarget: 'uploads/archive');
        $fileA = new FileInfo(type: FileInfo::TYPE_FILE, symlinkTarget: 'uploads/current');
        $fileB = new FileInfo(type: FileInfo::TYPE_FILE, symlinkTarget: 'uploads/archive');

        $t->true(!$linkA->isEquivalent($linkB));
        $t->true($fileA->isEquivalent($fileB), 'non-symlink targets are ignored upstream');

        $owned = new FileInfo(unixOwnerName: 'www-data', unixGroupName: 'www-data', unixUid: 33, unixGid: 33);
        $sameIds = new FileInfo(unixOwnerName: 'daemon', unixGroupName: 'daemon', unixUid: 33, unixGid: 33);
        $sameNames = new FileInfo(unixOwnerName: 'www-data', unixGroupName: 'www-data', unixUid: 1000, unixGid: 1000);
        $different = new FileInfo(unixOwnerName: 'wordpress', unixGroupName: 'wordpress', unixUid: 1000, unixGid: 1000);

        $t->true($owned->isEquivalent($sameIds));
        $t->true($owned->isEquivalent($sameNames));
        $t->true(!$owned->isEquivalent($different));
        $t->true(!$owned->isEquivalent(new FileInfo()));
        $t->true($owned->isEquivalent(new FileInfo(), new FileInfoComparison(ignoreOwnership: true)));
    },
    'keeps wordpress media metadata equivalent across scanner noise' => static function (TestRunner $t): void {
        $blockListHash = hash('sha256', hex2bin(hash('sha256', 'hero-bytes')));
        $current = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000200,
            version: VersionVector::fromCounters([101 => 3]),
            size: 10,
            blocksHash: $blockListHash,
            permissions: 0644,
            rawBlockSize: 32,
            sequence: 10,
            blocks: [new Block(0, 10, hash('sha256', 'hero-bytes'))],
        );
        $rescanned = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000200,
            version: VersionVector::fromCounters([202 => 1]),
            size: 10,
            blocksHash: $blockListHash,
            permissions: 0644,
            rawBlockSize: 64,
            sequence: 11,
            blocks: [new Block(0, 10, hash('sha256', 'hero-bytes'))],
        );
        $contentChanged = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000200,
            size: 10,
            permissions: 0644,
            blocks: [new Block(0, 10, hash('sha256', 'edited-hero'))],
        );

        $t->true($current->isEquivalent($rescanned));
        $t->true(!$current->isEquivalent($contentChanged));
        $t->true($current->isEquivalent(new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000200,
            size: 10,
            permissions: 0600,
            blocksHash: $blockListHash,
            blocks: [new Block(0, 10, hash('sha256', 'hero-bytes'))],
        ), new FileInfoComparison(ignorePerms: true)));
    },
];
