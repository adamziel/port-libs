<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\VersionVector;

return [
    'maps encrypted request geometry and opaque hash token fields' => static function (TestRunner $t): void {
        $encryptedName = ReceiveEncrypted::slashifyBase32Hex(str_repeat('A', 208));
        $hashToken = bin2hex('encrypted-block-token');
        $request = new Request(
            id: 31,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/encrypted-media.bin',
            offset: 3 * BlockList::MIN_BLOCK_SIZE,
            size: 512,
            hashHex: hash('sha256', 'plain-small-block'),
            fromTemporary: true,
            blockNo: 3,
        );

        $encrypted = ReceiveEncrypted::requestToEncryptedPeer($request, $encryptedName, $hashToken);

        $t->same($encryptedName, $encrypted->name);
        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, $encrypted->size);
        $t->same($request->offset + (3 * ReceiveEncrypted::BLOCK_OVERHEAD), $encrypted->offset);
        $t->same($hashToken, $encrypted->hashHex);
        $t->same(3, $encrypted->blockNo);
        $t->true(!$encrypted->fromTemporary);

        $decoded = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($encrypted));
        $t->same($encrypted->name, $decoded->name);
        $t->same($encrypted->offset, $decoded->offset);
        $t->same($encrypted->size, $decoded->size);
        $t->same($hashToken, $decoded->hashHex);
    },
    'maps encrypted inbound requests back to plaintext geometry' => static function (TestRunner $t): void {
        $realHash = hash('sha256', 'large plaintext block');
        $encrypted = new Request(
            id: 32,
            folder: 'wordpress-media',
            name: ReceiveEncrypted::slashifyBase32Hex(str_repeat('B', 96)),
            offset: (2 * BlockList::MIN_BLOCK_SIZE) + (2 * ReceiveEncrypted::BLOCK_OVERHEAD),
            size: 4096 + ReceiveEncrypted::BLOCK_OVERHEAD,
            hashHex: bin2hex('opaque-request-hash'),
            fromTemporary: true,
            blockNo: 2,
        );

        $plain = ReceiveEncrypted::requestFromEncryptedPeer($encrypted, 'wp-content/uploads/2026/plain-media.bin', $realHash);

        $t->same($encrypted->id, $plain->id);
        $t->same('wp-content/uploads/2026/plain-media.bin', $plain->name);
        $t->same(2 * BlockList::MIN_BLOCK_SIZE, $plain->offset);
        $t->same(4096, $plain->size);
        $t->same($realHash, $plain->hashHex);
        $t->true($plain->fromTemporary);
        $t->same(2, $plain->blockNo);

        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::requestFromEncryptedPeer(
            new Request(folder: 'wordpress-media', name: $encrypted->name, size: ReceiveEncrypted::MIN_PADDED_SIZE - 1),
            'wp-content/uploads/2026/plain-media.bin',
        ));
    },
    'maps upstream encrypted name slashification and parent detection' => static function (TestRunner $t): void {
        $token = '1' . 'AB' . str_repeat('C', ReceiveEncrypted::MAX_PATH_COMPONENT) . 'D2345';
        $path = ReceiveEncrypted::slashifyBase32Hex($token);

        $t->same('1.syncthing-enc/AB/' . str_repeat('C', ReceiveEncrypted::MAX_PATH_COMPONENT) . '/D2345', $path);
        $t->same($token, ReceiveEncrypted::deslashifyBase32HexPath($path));

        $fullComponent = str_repeat('Q', ReceiveEncrypted::MAX_PATH_COMPONENT);
        $cases = [
            '' => false,
            '.' => false,
            '/' => false,
            '12.syncthing-enc' => false,
            '1.syncthing-enc' => true,
            '1.syncthing-enc/b' => false,
            '1.syncthing-enc/bc' => true,
            '1.syncthing-enc/bcd' => false,
            '1.syncthing-enc/bc/foo' => false,
            '1.12/22' => false,
            '1.syncthing-enc/bc/' . $fullComponent => true,
            '1.syncthing-enc/bc/' . $fullComponent . '/' . $fullComponent => true,
            '1.syncthing-enc/bc/' . $fullComponent . 'A' => false,
            '1.syncthing-enc/bc/' . $fullComponent . '/a/' . $fullComponent => false,
        ];
        foreach ($cases as $candidate => $expected) {
            $t->same($expected, ReceiveEncrypted::isEncryptedParent($candidate), $candidate);
        }

        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::deslashifyBase32HexPath('T.wrong-extension/OD'));
        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::slashifyBase32Hex('not-base32'));
    },
    'writes and extracts upstream receive-encrypted file trailers' => static function (TestRunner $t): void {
        $bytes = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
        $blockList = new BlockList();
        $blocks = $blockList->fromBytes($bytes, 32);
        $file = new FileInfo(
            name: 'wp-content\\uploads\\2026\\encrypted-media.bin',
            modifiedS: 1700001600,
            version: VersionVector::fromCounters([77 => 9]),
            size: strlen($bytes),
            blocksHash: $blockList->hashBlocks($blocks),
            rawBlockSize: 32,
            sequence: 81,
            blocks: [
                new Block($blocks[0]->offset, $blocks[0]->size, $blocks[0]->hashHex),
                new Block($blocks[1]->offset, $blocks[1]->size, $blocks[1]->hashHex),
            ],
            modifiedBy: 77,
        );

        $trailer = ReceiveEncrypted::encryptionTrailer($file, '\\');
        $withTrailer = ReceiveEncrypted::appendEncryptionTrailer($bytes, $file, '\\');
        $extracted = ReceiveEncrypted::extractEncryptionTrailer($withTrailer);

        $t->same(strlen($bytes) + strlen($trailer), strlen($withTrailer));
        $t->same($bytes, $extracted['data']);
        $t->same(strlen($trailer), $extracted['trailerSize']);
        $t->same('wp-content/uploads/2026/encrypted-media.bin', $extracted['file']->name);
        $t->same($file->size, $extracted['file']->size);
        $t->same($file->blocksHash, $extracted['file']->blocksHash);
        $t->same($file->version->humanString(), $extracted['file']->version->humanString());

        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::appendEncryptionTrailer($bytes . 'extra', $file, '\\'));
        $t->throws(LengthException::class, static fn () => ReceiveEncrypted::extractEncryptionTrailer('abc'));
    },
];
