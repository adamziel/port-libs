<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;

return [
    'maps upstream check filename table cases' => static function (TestRunner $t): void {
        $valid = [
            'foo',
            'foo/bar/baz',
            'foo/bar:baz',
            '\\',
            '\\.',
            '\\..',
            '.foo',
            'foo..',
        ];
        foreach ($valid as $name) {
            ProtocolValidation::checkFilename($name);
            $t->true(ProtocolValidation::isValidFilename($name), $name);
        }

        $invalid = [
            'foo/..',
            'foo/../bar',
            '../foo/../bar',
            '',
            '.',
            '..',
            '/',
            '/.',
            '/..',
            '/foo',
            './foo',
            'foo./',
            'foo/.',
            'foo/',
        ];
        foreach ($invalid as $name) {
            $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkFilename($name), $name);
            $t->true(!ProtocolValidation::isValidFilename($name), $name);
        }
    },
    'maps upstream index file info consistency checks' => static function (TestRunner $t): void {
        $block = new Block(0, 7, hash('sha256', 'content'));
        $valid = new FileInfo(name: 'wp-content/uploads/hero.jpg', type: FileInfo::TYPE_FILE, size: 7, blocks: [$block]);
        ProtocolValidation::checkFileInfoConsistency($valid);
        ProtocolValidation::checkIndexConsistency([$valid]);
        $t->true(true);

        $deletedWithBlocks = new FileInfo(name: 'hero.jpg', deleted: true, type: FileInfo::TYPE_FILE, blocks: [$block]);
        $liveWithoutBlocks = new FileInfo(name: 'hero.jpg', type: FileInfo::TYPE_FILE);
        $directoryWithBlocks = new FileInfo(name: 'uploads', type: FileInfo::TYPE_DIRECTORY, blocks: [$block]);
        $invalidWithoutBlocks = new FileInfo(name: 'hero.jpg', type: FileInfo::TYPE_FILE, localFlags: FileInfo::FLAG_LOCAL_REMOTE_INVALID);

        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkFileInfoConsistency($deletedWithBlocks));
        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkFileInfoConsistency($liveWithoutBlocks));
        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkFileInfoConsistency($directoryWithBlocks));
        ProtocolValidation::checkFileInfoConsistency($invalidWithoutBlocks);
        $t->true(true);

        $badName = new FileInfo(name: '../escape.jpg', type: FileInfo::TYPE_FILE, blocks: [$block]);
        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkIndexConsistency([$badName]));
    },
    'maps upstream request size and filename validation' => static function (TestRunner $t): void {
        ProtocolValidation::checkRequest(new Request(name: 'valid', size: ProtocolValidation::MAX_REQUEST_SIZE));
        $t->true(true);

        foreach ([-65536, 0, ProtocolValidation::MAX_REQUEST_SIZE + 1] as $size) {
            $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkRequest(new Request(name: 'invalid', size: $size)));
        }

        $t->throws(InvalidArgumentException::class, static fn () => ProtocolValidation::checkRequest(new Request(name: '../escape', size: 1024)));
    },
    'normalizes outgoing wire paths for requests and index updates' => static function (TestRunner $t): void {
        $decomposed = 'Cafe' . "\u{0301}" . '.jpg';
        $composed = 'Caf' . "\u{00e9}" . '.jpg';
        $request = (new Request(name: 'wp-content\\uploads\\2026\\' . $decomposed, size: 1024))->normalizedForWire('\\');

        $t->same('wp-content/uploads/2026/' . $composed, $request->name);

        $block = new Block(0, 7, hash('sha256', 'content'));
        $file = new FileInfo(name: 'wp-content\\uploads\\2026\\' . $decomposed, type: FileInfo::TYPE_FILE, size: 7, blocks: [$block]);
        $update = new IndexUpdate('wordpress', [$file], lastSequence: 12, prevSequence: 11);
        $normalized = $update->normalizedForWire('\\');

        $t->same('wp-content/uploads/2026/' . $composed, $normalized->files[0]->name);
        $t->same(12, $normalized->lastSequence);
        $t->same(11, $normalized->prevSequence);
    },
    'keeps unix wire backslashes as literal filename characters' => static function (TestRunner $t): void {
        $t->same('\\.', ProtocolValidation::normalizeWireName('\\.', '/'));
        ProtocolValidation::checkFilename('\\.');
        $t->true(true);
    },
];
