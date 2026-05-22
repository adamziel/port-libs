<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream file info conflict winner ordering' => static function (TestRunner $t): void {
        $cases = [
            [
                new FileInfo(modifiedS: 42),
                new FileInfo(modifiedS: 41),
            ],
            [
                new FileInfo(modifiedS: 42, deleted: true),
                new FileInfo(modifiedS: 41),
            ],
            [
                new FileInfo(deleted: true),
                new FileInfo(modifiedS: 10, localFlags: FileInfo::FLAG_LOCAL_REMOTE_INVALID),
            ],
            [
                new FileInfo(modifiedS: 41, version: VersionVector::fromCounters([42 => 2, 43 => 1])),
                new FileInfo(modifiedS: 41, version: VersionVector::fromCounters([42 => 1, 43 => 2])),
            ],
        ];

        foreach ($cases as [$winner, $loser]) {
            $t->true($winner->winsConflict($loser));
            $t->true(!$loser->winsConflict($winner));
        }
    },
    'detects conflicts from vector ancestry and block hash lineage' => static function (TestRunner $t): void {
        $oldHash = hash('sha256', 'old content');
        $newHash = hash('sha256', 'new content');
        $otherHash = hash('sha256', 'other content');

        $previous = new FileInfo(
            name: 'wp-content/uploads/hero.jpg',
            version: VersionVector::fromCounters([1 => 3]),
            blocksHash: $oldHash,
        );

        $descendant = new FileInfo(
            name: 'wp-content/uploads/hero.jpg',
            version: VersionVector::fromCounters([1 => 4]),
            blocksHash: $newHash,
            previousBlocksHash: $oldHash,
        );
        $t->true(!$descendant->inConflictWith($previous));

        $rebasedConcurrent = new FileInfo(
            name: 'wp-content/uploads/hero.jpg',
            version: VersionVector::fromCounters([1 => 3, 2 => 1]),
            blocksHash: $newHash,
            previousBlocksHash: $oldHash,
        );
        $t->true(!$rebasedConcurrent->inConflictWith($previous));

        $staleConcurrent = new FileInfo(
            name: 'wp-content/uploads/hero.jpg',
            version: VersionVector::fromCounters([1 => 2, 2 => 1]),
            blocksHash: $newHash,
            previousBlocksHash: $otherHash,
        );
        $t->true($staleConcurrent->inConflictWith($previous));

        $typeChanged = new FileInfo(
            name: 'wp-content/uploads/hero.jpg',
            version: VersionVector::fromCounters([2 => 1]),
            blocksHash: '',
            previousBlocksHash: '',
        );
        $t->true($typeChanged->inConflictWith($previous));
    },
    'maps local invalid and conflict flag handling' => static function (TestRunner $t): void {
        $clean = new FileInfo(localFlags: 0);
        $remoteInvalid = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_REMOTE_INVALID);
        $receiveOnly = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY);
        $mustRescan = new FileInfo(localFlags: FileInfo::FLAG_LOCAL_MUST_RESCAN);

        $t->true(!$clean->isInvalid());
        $t->true(!$clean->shouldConflict());
        $t->true($remoteInvalid->isInvalid());
        $t->true(!$remoteInvalid->shouldConflict());
        $t->true($receiveOnly->isInvalid());
        $t->true($receiveOnly->shouldConflict());
        $t->true($mustRescan->isInvalid());
        $t->true(!$mustRescan->shouldConflict());
    },
    'creates tombstones that clear content and advance device versions' => static function (TestRunner $t): void {
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            modifiedS: 1700000000,
            version: VersionVector::fromCounters([101 => 1700000000]),
            size: 123,
            blocksHash: hash('sha256', 'hero-bytes'),
        );

        $deleted = $file->withDeleted(101, 1700000100);
        $t->true($deleted->isDeleted());
        $t->same(0, $deleted->size);
        $t->same('', $deleted->blocksHash);
        $t->same([101 => 1700000100], $deleted->version->toArray());
        $t->same(1700000100, $deleted->modifiedS);
        $t->true($deleted->winsConflict($file));
    },
    'rejects malformed file info hashes' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => new FileInfo(blocksHash: 'not-a-hash'));
        $t->throws(InvalidArgumentException::class, static fn () => new FileInfo(previousBlocksHash: strtoupper(hash('sha256', 'x'))));
    },
];
