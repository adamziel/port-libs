<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\EncryptedDownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Index;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\RequestServingResult;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;
use PortLibs\Syncthing\WireProgressConnection;

return [
    'maps upstream XChaCha20 encrypted bytes fixture' => static function (TestRunner $t): void {
        $folderKey = EncryptionKey::folderKeyFromPassword('my folder', 'my password');
        $fileKey = ReceiveEncrypted::fileKey('filename.txt', $folderKey);
        $decodeBase32Hex = static function (string $token): string {
            $alphabet = array_flip(str_split('0123456789ABCDEFGHIJKLMNOPQRSTUV'));
            $buffer = 0;
            $bits = 0;
            $out = '';

            foreach (str_split($token) as $char) {
                $buffer = ($buffer << 5) | $alphabet[$char];
                $bits += 5;
                if ($bits >= 8) {
                    $out .= chr(($buffer >> ($bits - 8)) & 0xff);
                    $bits -= 8;
                    $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
                }
            }

            return $out;
        };

        $fixture = 'A1IPD28ISL7VNPRSSSQM2L31L3IJPC08283RO89J5UG0TI9P38DO9RFGK12DK0KD7PKQP6U51UL2B6H96O';
        $encrypted = $decodeBase32Hex($fixture);

        $t->same('hello world', ReceiveEncrypted::decryptBytes($encrypted, $fileKey));

        $nonce = str_repeat("\0", ReceiveEncrypted::NONCE_SIZE);
        $roundTrip = ReceiveEncrypted::encryptBytes('wordpress media plaintext', $fileKey, $nonce);
        $t->same(ReceiveEncrypted::NONCE_SIZE + strlen('wordpress media plaintext') + ReceiveEncrypted::TAG_SIZE, strlen($roundTrip));
        $t->same($nonce, substr($roundTrip, 0, ReceiveEncrypted::NONCE_SIZE));
        $t->same('wordpress media plaintext', ReceiveEncrypted::decryptBytes($roundTrip, $fileKey));
        $tamperedRoundTrip = substr($roundTrip, 0, -1) . chr(ord(substr($roundTrip, -1)) ^ 1);
        $t->throws(RuntimeException::class, static fn () => ReceiveEncrypted::decryptBytes($tamperedRoundTrip, $fileKey));
        $t->throws(LengthException::class, static fn () => ReceiveEncrypted::decryptBytes('short', $fileKey));
    },
    'maps encrypted request geometry and opaque hash token fields' => static function (TestRunner $t): void {
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
        $folderKey = EncryptionKey::folderKeyFromPassword($request->folder, 'wordpress media sync secret');
        $fileKey = ReceiveEncrypted::fileKey($request->name, $folderKey);
        $encryptedName = ReceiveEncrypted::encryptName($request->name, $folderKey);
        $hashToken = ReceiveEncrypted::encryptBlockHashHex($request->hashHex, $request->offset, $fileKey);

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
        $t->same($request->name, ReceiveEncrypted::decryptName($decoded->name, $folderKey));
        $t->same($request->hashHex, ReceiveEncrypted::decryptBlockHashHex($decoded->hashHex, $request->offset, $fileKey));
    },
    'maps encryptedConnection request response padding and trim semantics' => static function (TestRunner $t): void {
        $plainBytes = 'wordpress encrypted media preview block';
        $request = new Request(
            id: 41,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/encrypted-preview.jpg',
            offset: 4 * BlockList::MIN_BLOCK_SIZE,
            size: strlen($plainBytes),
            hashHex: hash('sha256', $plainBytes),
            fromTemporary: true,
            blockNo: 4,
        );
        $folderKey = EncryptionKey::folderKeyFromPassword($request->folder, 'wordpress media sync secret');
        $fileKey = ReceiveEncrypted::fileKey($request->name, $folderKey);

        $encryptedRequest = ReceiveEncrypted::encryptRequestForEncryptedPeer($request, $folderKey);
        $decodedRequest = BepWire::decodeRequestMessage(BepWire::encodeRequestMessage($encryptedRequest));

        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, $decodedRequest->size);
        $t->same($request->offset + (4 * ReceiveEncrypted::BLOCK_OVERHEAD), $decodedRequest->offset);
        $t->true(!$decodedRequest->fromTemporary);
        $t->same($request->name, ReceiveEncrypted::decryptName($decodedRequest->name, $folderKey));
        $t->same($request->hashHex, ReceiveEncrypted::decryptBlockHashHex($decodedRequest->hashHex, $request->offset, $fileKey));

        $encryptedResponse = ReceiveEncrypted::encryptResponseForEncryptedPeer(
            new Response($request->id, $plainBytes),
            $fileKey,
            str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE),
            str_repeat("\4", ReceiveEncrypted::NONCE_SIZE),
        );

        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, strlen($encryptedResponse->data));
        $t->true(!str_contains($encryptedResponse->data, $plainBytes));
        $paddedPlaintext = ReceiveEncrypted::decryptBytes($encryptedResponse->data, $fileKey);
        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE, strlen($paddedPlaintext));
        $t->same($plainBytes, substr($paddedPlaintext, 0, strlen($plainBytes)));
        $t->same(str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)), substr($paddedPlaintext, strlen($plainBytes)));

        $trimmed = ReceiveEncrypted::decryptResponseFromEncryptedPeer($encryptedResponse, $fileKey, $request->size);
        $t->same($request->id, $trimmed->id);
        $t->same(Response::CODE_NO_ERROR, $trimmed->code);
        $t->same($plainBytes, $trimmed->data);

        $shortEncrypted = new Response(
            $request->id,
            ReceiveEncrypted::encryptBytes('short', $fileKey, str_repeat("\5", ReceiveEncrypted::NONCE_SIZE)),
        );
        $t->throws(LengthException::class, static fn () => ReceiveEncrypted::decryptResponseFromEncryptedPeer($shortEncrypted, $fileKey, 6));
    },
    'maps encryptedModel inbound request decryption and response encryption' => static function (TestRunner $t): void {
        $servedBytes = str_repeat('large-wordpress-media-chunk-', 50);
        $request = new Request(
            id: 42,
            folder: 'wordpress-media',
            name: 'wp-content/uploads/2026/private-export.bin',
            offset: 2 * BlockList::MIN_BLOCK_SIZE,
            size: strlen($servedBytes),
            hashHex: hash('sha256', $servedBytes),
            blockNo: 2,
        );
        $folderKey = EncryptionKey::folderKeyFromPassword($request->folder, 'wordpress media sync secret');
        $fileKey = ReceiveEncrypted::fileKey($request->name, $folderKey);

        $encryptedRequest = ReceiveEncrypted::encryptRequestForEncryptedPeer($request, $folderKey);
        $plainRequest = ReceiveEncrypted::decryptRequestFromEncryptedPeer($encryptedRequest, $folderKey);

        $t->same($request->id, $plainRequest->id);
        $t->same($request->name, $plainRequest->name);
        $t->same($request->offset, $plainRequest->offset);
        $t->same($request->size, $plainRequest->size);
        $t->same($request->hashHex, $plainRequest->hashHex);
        $t->true(!$plainRequest->fromTemporary);
        $t->same($request->blockNo, $plainRequest->blockNo);

        $encryptedResponse = ReceiveEncrypted::encryptResponseForEncryptedPeer(
            new Response($plainRequest->id, $servedBytes),
            $fileKey,
            nonce: str_repeat("\6", ReceiveEncrypted::NONCE_SIZE),
        );
        $t->same(strlen($servedBytes) + ReceiveEncrypted::BLOCK_OVERHEAD, strlen($encryptedResponse->data));
        $roundTrip = ReceiveEncrypted::decryptResponseFromEncryptedPeer($encryptedResponse, $fileKey, $request->size);
        $t->same($servedBytes, $roundTrip->data);

        $error = new Response($plainRequest->id, '', Response::CODE_NO_SUCH_FILE);
        $t->same($error, ReceiveEncrypted::encryptResponseForEncryptedPeer($error, $fileKey));
        $t->same($error, ReceiveEncrypted::decryptResponseFromEncryptedPeer($error, $fileKey, $request->size));
    },
    'serves encryptedModel requests through native request server' => static function (TestRunner $t): void {
        $root = syncthing_receive_encrypted_request_root();
        try {
            $bytes = 'private WordPress media block served through encryptedModel.Request';
            $name = 'wp-content/uploads/2026/private/encrypted-request.jpg';
            syncthing_receive_encrypted_request_write($root, $name, $bytes);

            $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-media', 'wordpress media sync secret');
            $fileKey = ReceiveEncrypted::fileKey($name, $folderKey);
            $plainRequest = new Request(
                id: 51,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: strlen($bytes),
                hashHex: hash('sha256', $bytes),
                blockNo: 0,
            );
            $encryptedRequest = BepWire::decodeRequestMessage(
                BepWire::encodeRequestMessage(ReceiveEncrypted::encryptRequestForEncryptedPeer($plainRequest, $folderKey)),
            );
            $server = new RequestServer('wordpress-media', $root, ['untrusted-peer']);

            $result = ReceiveEncrypted::serveEncryptedRequestFromPeer(
                $server,
                'untrusted-peer',
                $encryptedRequest,
                $folderKey,
                str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE),
                str_repeat(chr(13), ReceiveEncrypted::NONCE_SIZE),
            );
            $decodedFrame = BepWire::decodeResponseMessage(BepWire::encodeResponseMessage($result->response));
            $trustedResponse = ReceiveEncrypted::decryptResponseFromEncryptedPeer($decodedFrame, $fileKey, strlen($bytes));
            $paddedPlaintext = ReceiveEncrypted::decryptBytes($decodedFrame->data, $fileKey);

            $t->true($result->successful());
            $t->same(RequestServingResult::SOURCE_FINAL, $result->source);
            $t->same(Response::CODE_NO_ERROR, $decodedFrame->code);
            $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, strlen($decodedFrame->data));
            $t->same($bytes, $trustedResponse->data);
            $t->same(ReceiveEncrypted::MIN_PADDED_SIZE, strlen($paddedPlaintext));
            $t->same($bytes, substr($paddedPlaintext, 0, strlen($bytes)));

            $wrongHashRequest = ReceiveEncrypted::encryptRequestForEncryptedPeer(new Request(
                id: 52,
                folder: 'wordpress-media',
                name: $name,
                offset: 0,
                size: strlen($bytes),
                hashHex: hash('sha256', 'not the bytes on disk'),
                blockNo: 0,
            ), $folderKey);
            $mismatch = ReceiveEncrypted::serveEncryptedRequestFromPeer($server, 'untrusted-peer', $wrongHashRequest, $folderKey);
            $t->same(Response::CODE_NO_SUCH_FILE, $mismatch->response->code);
            $t->same(RequestServingResult::SOURCE_NONE, $mismatch->source);
            $t->same('hash mismatch', $mismatch->reason);
            $t->same('', $mismatch->response->data);

            $invalid = ReceiveEncrypted::serveEncryptedRequestFromPeer(
                $server,
                'untrusted-peer',
                new Request(id: 53, folder: 'wordpress-media', name: 'T.wrong-extension/OD', size: ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD),
                $folderKey,
            );
            $t->same(Response::CODE_GENERIC, $invalid->response->code);
            $t->same('decrypting encrypted request failed', $invalid->reason);
        } finally {
            syncthing_receive_encrypted_request_rm($root);
        }
    },
    'maps upstream deterministic encrypted name fixtures and invalid cases' => static function (TestRunner $t): void {
        $key = str_repeat("\0", EncryptionKey::KEY_SIZE);
        $pattern = '/^[0-9A-V]\.syncthing-enc\/[0-9A-V]{2}\/(?:[0-9A-V]{200}\/)*[0-9A-V]{1,199}$/';
        $makeName = static function (int $length): string {
            $name = '';
            for ($i = 0; $i < $length; $i++) {
                $name .= chr(ord('a') + ($i % 26));
            }
            return $name;
        };

        foreach ([
            '',
            'foo',
            'a longer name/with/slashes and spaces',
            $makeName(ReceiveEncrypted::MAX_PATH_COMPONENT),
            $makeName(1 + ReceiveEncrypted::MAX_PATH_COMPONENT),
            $makeName(2 * ReceiveEncrypted::MAX_PATH_COMPONENT),
            $makeName(1 + (2 * ReceiveEncrypted::MAX_PATH_COMPONENT)),
        ] as $plainName) {
            $encrypted = ReceiveEncrypted::encryptName($plainName, $key);

            $t->same($encrypted, ReceiveEncrypted::encryptName($plainName, $key), 'encrypted names must be deterministic');
            $t->same(1, preg_match($pattern, $encrypted), $encrypted);
            $t->same($plainName, ReceiveEncrypted::decryptName($encrypted, $key));
            if ($plainName !== '') {
                $t->true(!str_contains($encrypted, $plainName), 'encrypted name should not contain plaintext');
            }
        }

        $folderKey = EncryptionKey::folderKeyFromPassword('my folder', 'my password');
        $fixtureName = '3.syncthing-enc/T5/957I4IOA20VEIEER6JSQG0PEPIRV862II3K7LOF75Q';
        $t->same($fixtureName, ReceiveEncrypted::encryptName('filename.txt', $folderKey));
        $t->same('filename.txt', ReceiveEncrypted::decryptName($fixtureName, $folderKey));

        foreach ([
            'T.syncthing-enc/OD',
            'T.syncthing-enc/OD/',
            'T.wrong-extension/OD/PHVDD67S7FI2K5QQMPSOFSK',
            'OD/PHVDD67S7FI2K5QQMPSOFSK',
        ] as $invalidName) {
            $t->throws(Throwable::class, static fn () => ReceiveEncrypted::decryptName($invalidName, $key));
        }
    },
    'maps upstream deterministic block hash token invariants' => static function (TestRunner $t): void {
        $folderKey = EncryptionKey::folderKeyFromPassword('my folder', 'my password');
        $fileKey = ReceiveEncrypted::fileKey('filename.txt', $folderKey);
        $hashHex = hash('sha256', 'wordpress encrypted block bytes');

        $firstOffsetToken = ReceiveEncrypted::encryptBlockHashHex($hashHex, 0, $fileKey);
        $sameOffsetToken = ReceiveEncrypted::encryptBlockHashHex($hashHex, 0, $fileKey);
        $nextOffsetToken = ReceiveEncrypted::encryptBlockHashHex($hashHex, 45, $fileKey);

        $t->same(96, strlen($firstOffsetToken));
        $t->same($firstOffsetToken, $sameOffsetToken, 'same hash and offset should produce a stable token');
        $t->true($firstOffsetToken !== $nextOffsetToken, 'same hash at different offsets should not reuse tokens');
        $t->same($hashHex, ReceiveEncrypted::decryptBlockHashHex($firstOffsetToken, 0, $fileKey));
        $t->same($hashHex, ReceiveEncrypted::decryptBlockHashHex($nextOffsetToken, 45, $fileKey));
        $t->throws(RuntimeException::class, static fn () => ReceiveEncrypted::decryptBlockHashHex($firstOffsetToken, 45, $fileKey));

        $legacyToken = bin2hex(EncryptionKey::encryptDeterministic(hex2bin($hashHex), $fileKey));
        $t->same($hashHex, ReceiveEncrypted::decryptBlockHashHex($legacyToken, 45, $fileKey));
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
    'maps receive-encrypted synthetic parent scan cleanup' => static function (TestRunner $t): void {
        $root = sys_get_temp_dir() . '/syncthing-recvenc-scan-' . bin2hex(random_bytes(6));
        $cleanup = static function (string $path) use (&$cleanup): void {
            if (is_link($path) || is_file($path)) {
                unlink($path);
                return;
            }
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $cleanup($path . DIRECTORY_SEPARATOR . $entry);
            }
            rmdir($path);
        };

        mkdir($root, 0777, true);
        try {
            $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret');
            $encryptedName = ReceiveEncrypted::encryptName('wp-content/uploads/private/parent-cleanup.jpg', $folderKey);
            $parent = dirname($encryptedName);
            $created = ReceiveEncrypted::ensureReceiveEncryptedParentDirectory($root, $encryptedName);

            $t->same($parent, $created['parent']);
            $t->true($created['created']);
            $t->true(!$created['scanAfterPull']);
            $t->true(is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parent)));

            $again = ReceiveEncrypted::ensureReceiveEncryptedParentDirectory($root, $encryptedName);
            $t->true(!$again['created']);
            $t->true(!$again['scanAfterPull']);

            file_put_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $encryptedName), 'encrypted media bytes');
            $nonEmpty = ReceiveEncrypted::receiveEncryptedScanUpdate(new FileInfo(
                name: $parent,
                type: FileInfo::TYPE_DIRECTORY,
                localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
            ), $root);
            $t->same(null, $nonEmpty['file']);
            $t->true($nonEmpty['syntheticParent']);
            $t->true(!$nonEmpty['removedEmptyParent']);
            $t->true(is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parent)));

            $emptyParent = '2.syncthing-enc/AA/' . str_repeat('B', ReceiveEncrypted::MAX_PATH_COMPONENT);
            mkdir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $emptyParent), 0777, true);
            $empty = ReceiveEncrypted::receiveEncryptedScanUpdate(new FileInfo(
                name: $emptyParent,
                type: FileInfo::TYPE_DIRECTORY,
                localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
            ), $root);
            $t->same(null, $empty['file']);
            $t->true($empty['syntheticParent']);
            $t->true($empty['removedEmptyParent']);
            $t->true(!is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $emptyParent)));

            $realDirectory = new FileInfo(name: 'wp-content/uploads/private', type: FileInfo::TYPE_DIRECTORY);
            $real = ReceiveEncrypted::receiveEncryptedScanUpdate($realDirectory, $root);
            $t->same($realDirectory, $real['file']);
            $t->true(!$real['syntheticParent']);
            $t->true(!$real['removedEmptyParent']);

            $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::removeEmptySyntheticParentDirectory($root, 'wp-content/uploads'));
            $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::ensureReceiveEncryptedParentDirectory($root, 'wp-content/uploads/plain.jpg'));
        } finally {
            $cleanup($root);
        }
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
    'maps receive-encrypted finalization trailer and verification boundaries' => static function (TestRunner $t): void {
        $plainBytes = str_repeat('wordpress-finalized-private-media-', 20);
        $blockList = new BlockList();
        $blocks = $blockList->fromBytes($plainBytes, strlen($plainBytes));
        $plainFile = new FileInfo(
            name: 'wp-content\\uploads\\2026\\private\\finalized-media.bin',
            modifiedS: 1700002100,
            version: VersionVector::fromCounters([77 => 14]),
            size: strlen($plainBytes),
            blocksHash: $blockList->hashBlocks($blocks),
            rawBlockSize: strlen($plainBytes),
            sequence: 141,
            blocks: $blocks,
            modifiedBy: 77,
        );
        $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret');
        $fileKey = ReceiveEncrypted::fileKey('wp-content/uploads/2026/private/finalized-media.bin', $folderKey);
        $encryptedFile = ReceiveEncrypted::encryptFileInfo(
            $plainFile->withName('wp-content/uploads/2026/private/finalized-media.bin'),
            $folderKey,
            str_repeat("\10", ReceiveEncrypted::NONCE_SIZE),
        );
        $paddedPlainBytes = $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes));
        $encryptedData = ReceiveEncrypted::encryptBytes(
            $paddedPlainBytes,
            $fileKey,
            str_repeat("\11", ReceiveEncrypted::NONCE_SIZE),
        );

        $finalized = ReceiveEncrypted::finalizeEncryptedFile($encryptedData, $encryptedFile);
        $remoteIndexFile = ReceiveEncrypted::prepareFinalizedFileInfoForIndex($finalized['file'], $finalized['trailerSize']);
        $verified = ReceiveEncrypted::verifyFinalizedEncryptedFile($finalized['bytes'], $folderKey);

        $t->same($encryptedFile->size, strlen($encryptedData));
        $t->same($encryptedFile->size + $finalized['trailerSize'], strlen($finalized['bytes']));
        $t->same($encryptedFile->size + $finalized['trailerSize'], $finalized['file']->size);
        $t->same($encryptedFile->size, $remoteIndexFile->size);
        $t->same($encryptedFile->name, $verified['encryptedFile']->name);
        $t->same($encryptedData, $verified['encryptedData']);
        $t->same($plainBytes, $verified['plaintext']);
        $t->same('wp-content/uploads/2026/private/finalized-media.bin', $verified['plainFile']->name);
        $t->same($plainFile->blocksHash, $verified['plainFile']->blocksHash);
        $t->same(141, $verified['plainFile']->sequence);

        $insertedGarbage = $encryptedData . 'x' . substr($finalized['bytes'], strlen($encryptedData));
        $t->throws(LengthException::class, static fn () => ReceiveEncrypted::verifyFinalizedEncryptedFile($insertedGarbage, $folderKey));

        $tampered = substr($finalized['bytes'], 0, 30)
            . chr(ord($finalized['bytes'][30]) ^ 1)
            . substr($finalized['bytes'], 31);
        $t->throws(RuntimeException::class, static fn () => ReceiveEncrypted::verifyFinalizedEncryptedFile($tampered, $folderKey));

        $missingMetadata = new FileInfo(
            name: $encryptedFile->name,
            size: strlen($encryptedData),
            blocks: $encryptedFile->blocks,
        );
        $badTrailer = ReceiveEncrypted::appendEncryptionTrailer($encryptedData, $missingMetadata);
        $t->throws(UnexpectedValueException::class, static fn () => ReceiveEncrypted::verifyFinalizedEncryptedFile($badTrailer, $folderKey));
        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::finalizeEncryptedFile($encryptedData, $missingMetadata));
        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::prepareFinalizedFileInfoForIndex($finalized['file'], -1));
        $t->throws(LengthException::class, static fn () => ReceiveEncrypted::prepareFinalizedFileInfoForIndex($finalized['file'], $finalized['file']->size + 1));
    },
    'maps upstream encrypted file info wrapper invariants' => static function (TestRunner $t): void {
        $folderKey = str_repeat("\0", EncryptionKey::KEY_SIZE);
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/encrypted-media.bin',
            modifiedS: 8080,
            version: VersionVector::fromCounters([42 => 7, 77 => 5]),
            size: 90,
            permissions: 0755,
            rawBlockSize: 45,
            sequence: 1000,
            blocks: [
                new Block(0, 45, bin2hex("\x01\x02\x03")),
                new Block(45, 45, bin2hex("\x01\x02\x03")),
            ],
        );

        $encrypted = ReceiveEncrypted::encryptFileInfo($file, $folderKey, str_repeat("\1", ReceiveEncrypted::NONCE_SIZE));
        $again = ReceiveEncrypted::encryptFileInfo($file, $folderKey, str_repeat("\2", ReceiveEncrypted::NONCE_SIZE));

        $t->same('wp-content/uploads/2026/encrypted-media.bin', ReceiveEncrypted::decryptName($encrypted->name, $folderKey));
        $t->same(FileInfo::TYPE_FILE, $encrypted->type);
        $t->same(0644, $encrypted->permissions);
        $t->same(1234567890, $encrypted->modifiedS);
        $t->same([1 => 12], $encrypted->version->toArray());
        $t->same(1000, $encrypted->sequence);
        $t->same(2 * (ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD), $encrypted->size);
        $t->same(BlockList::MIN_BLOCK_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, $encrypted->rawBlockSize);
        $t->same(2, count($encrypted->blocks));
        $t->same(0, $encrypted->blocks[0]->offset);
        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, $encrypted->blocks[1]->offset);
        $t->same(ReceiveEncrypted::MIN_PADDED_SIZE + ReceiveEncrypted::BLOCK_OVERHEAD, $encrypted->blocks[0]->size);
        $t->true($encrypted->blocks[0]->hashHex !== $encrypted->blocks[1]->hashHex);
        $t->same($encrypted->blocks[0]->hashHex, $again->blocks[0]->hashHex);
        $t->same($encrypted->blocks[1]->hashHex, $again->blocks[1]->hashHex);
        $t->same(ReceiveEncrypted::NONCE_SIZE + strlen(BepWire::encodeFileInfoPayload($file)) + ReceiveEncrypted::TAG_SIZE, strlen($encrypted->encryptedPayload));
        $t->true(!str_contains($encrypted->encryptedPayload, $file->name));

        $decodedWire = BepWire::decodeFileInfoPayload(BepWire::encodeFileInfoPayload($encrypted));
        $t->same($encrypted->encryptedPayload, $decodedWire->encryptedPayload);

        $remoteSequenced = $decodedWire->withSequence(10);
        $decrypted = ReceiveEncrypted::decryptFileInfo($remoteSequenced, $folderKey);
        $t->same(10, $decrypted->sequence);
        $decrypted = $decrypted->withSequence($file->sequence);
        $t->same($file->name, $decrypted->name);
        $t->same($file->permissions, $decrypted->permissions);
        $t->same($file->modifiedS, $decrypted->modifiedS);
        $t->same($file->version->toArray(), $decrypted->version->toArray());
        $t->same($file->blocks[0]->hashHex, $decrypted->blocks[0]->hashHex);
        $t->same($file->blocks[1]->hashHex, $decrypted->blocks[1]->hashHex);

        $tamperedPayload = substr($encrypted->encryptedPayload, 0, -1) . chr(ord(substr($encrypted->encryptedPayload, -1)) ^ 1);
        $tampered = new FileInfo(
            name: $encrypted->name,
            modifiedS: $encrypted->modifiedS,
            version: $encrypted->version,
            size: $encrypted->size,
            type: $encrypted->type,
            permissions: $encrypted->permissions,
            rawBlockSize: $encrypted->rawBlockSize,
            sequence: $encrypted->sequence,
            blocks: $encrypted->blocks,
            encryptedPayload: $tamperedPayload,
        );
        $t->throws(RuntimeException::class, static fn () => ReceiveEncrypted::decryptFileInfo($tampered, $folderKey));
    },
    'maps encrypted index and index update collection wrappers' => static function (TestRunner $t): void {
        $folderKey = str_repeat("\0", EncryptionKey::KEY_SIZE);
        $blocks = (new BlockList())->fromBytes('wordpress encrypted index bytes', 12);
        $file = new FileInfo(
            name: 'wp-content\\uploads\\2026\\encrypted-index.bin',
            modifiedS: 1700001800,
            version: VersionVector::fromCounters([42 => 8, 77 => 3]),
            size: strlen('wordpress encrypted index bytes'),
            blocksHash: (new BlockList())->hashBlocks($blocks),
            permissions: 0644,
            rawBlockSize: 12,
            sequence: 120,
            blocks: [
                new Block($blocks[0]->offset, $blocks[0]->size, $blocks[0]->hashHex),
                new Block($blocks[1]->offset, $blocks[1]->size, $blocks[1]->hashHex),
                new Block($blocks[2]->offset, $blocks[2]->size, $blocks[2]->hashHex),
            ],
        );
        $index = new Index('wordpress-media', [$file], lastSequence: 120);

        $encryptedIndex = ReceiveEncrypted::encryptIndex($index, $folderKey, '\\');
        $encryptedFile = $encryptedIndex->files[0];
        ProtocolValidation::checkIndexConsistency($encryptedIndex->files);

        $t->same('wordpress-media', $encryptedIndex->folder);
        $t->same(120, $encryptedIndex->lastSequence);
        $t->true(!str_contains($encryptedFile->name, 'encrypted-index.bin'));
        $t->same('wp-content/uploads/2026/encrypted-index.bin', ReceiveEncrypted::decryptName($encryptedFile->name, $folderKey));
        $t->same(1234567890, $encryptedFile->modifiedS);
        $t->same([1 => 11], $encryptedFile->version->toArray());
        $t->same(120, $encryptedFile->sequence);
        $t->same('', $encryptedFile->blocksHash);
        $t->same(ReceiveEncrypted::NONCE_SIZE + strlen(BepWire::encodeFileInfoPayload($file->withName('wp-content/uploads/2026/encrypted-index.bin'))) + ReceiveEncrypted::TAG_SIZE, strlen($encryptedFile->encryptedPayload));

        $decodedEncryptedIndex = BepWire::decodeIndexMessage(BepWire::encodeIndexMessage($encryptedIndex));
        $decryptedIndex = ReceiveEncrypted::decryptIndex($decodedEncryptedIndex, $folderKey);

        $t->same(120, $decryptedIndex->lastSequence);
        $t->same('wp-content/uploads/2026/encrypted-index.bin', $decryptedIndex->files[0]->name);
        $t->same($file->version->toArray(), $decryptedIndex->files[0]->version->toArray());
        $t->same($file->blocks[2]->hashHex, $decryptedIndex->files[0]->blocks[2]->hashHex);
        $t->same(120, $decryptedIndex->files[0]->sequence);

        $update = new IndexUpdate('wordpress-media', [$file], lastSequence: 123, prevSequence: 120);
        $decodedEncryptedUpdate = BepWire::decodeIndexUpdateMessage(
            BepWire::encodeIndexUpdateMessage(ReceiveEncrypted::encryptIndexUpdate($update, $folderKey, '\\')),
        );
        $decryptedUpdate = ReceiveEncrypted::decryptIndexUpdate($decodedEncryptedUpdate, $folderKey);

        $t->same(123, $decryptedUpdate->lastSequence);
        $t->same(120, $decryptedUpdate->prevSequence);
        $t->same('wp-content/uploads/2026/encrypted-index.bin', $decryptedUpdate->files[0]->name);
        $t->same($file->blocksHash, $decryptedUpdate->files[0]->blocksHash);
        $t->same(120, $decryptedUpdate->files[0]->sequence);

        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::encryptFileInfos([new stdClass()], $folderKey));
        $t->throws(InvalidArgumentException::class, static fn () => ReceiveEncrypted::decryptFileInfos([new stdClass()], $folderKey));
    },
    'maps encryptedConnection DownloadProgress no-op for encrypted folders' => static function (TestRunner $t): void {
        $router = EncryptedDownloadProgress::fromPasswords([
            'wordpress-private-media' => 'wordpress media sync secret',
        ]);
        $version = VersionVector::fromCounters([42 => 1]);
        $privateProgress = new DownloadProgress('wordpress-private-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content\\uploads\\private\\hero.jpg',
                version: $version,
                blockIndexes: [0, 2],
                blockSize: BlockList::MIN_BLOCK_SIZE,
            ),
        ]);
        $publicProgress = new DownloadProgress('wordpress-public-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content\\uploads\\public\\hero.jpg',
                version: $version,
                blockIndexes: [1],
                blockSize: BlockList::MIN_BLOCK_SIZE,
            ),
        ]);
        $frames = [];
        $connection = new WireProgressConnection(
            'untrusted-peer',
            static function (string $deviceId, string $frame, DownloadProgress $progress) use (&$frames): void {
                $frames[] = [$deviceId, $frame, $progress];
            },
            directorySeparator: '\\',
        );

        $t->true($router->hasFolderKey('wordpress-private-media'));
        $t->same(null, $router->outgoingToEncryptedPeer($privateProgress));
        $t->same($publicProgress, $router->outgoingToEncryptedPeer($publicProgress));
        $t->true(!$router->sendOutgoing($connection, $privateProgress));
        $t->same([], $frames);

        $t->true($router->sendOutgoing($connection, $publicProgress));
        $t->same(1, count($frames));
        $t->same('untrusted-peer', $frames[0][0]);
        $decoded = BepWire::decodeDownloadProgressMessage($frames[0][1]);
        $t->same('wordpress-public-media', $decoded->folder);
        $t->same('wp-content/uploads/public/hero.jpg', $decoded->updates[0]->name);
        $t->same([1], $decoded->updates[0]->blockIndexes);

        $t->throws(LengthException::class, static fn () => new EncryptedDownloadProgress(['wordpress-private-media' => 'short']));
    },
    'maps encryptedModel DownloadProgress no-op before temporary state mutation' => static function (TestRunner $t): void {
        $router = new EncryptedDownloadProgress([
            'wordpress-private-media' => EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret'),
        ]);
        $version = VersionVector::fromCounters([42 => 2]);
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-private-media' => ['untrusted-peer'],
            'wordpress-public-media' => ['trusted-peer'],
        ]);
        $privateProgress = new DownloadProgress('wordpress-private-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content/uploads/private/hero.jpg',
                version: $version,
                blockIndexes: [0, 1, 2],
                blockSize: 4096,
            ),
        ]);
        $publicProgress = new DownloadProgress('wordpress-public-media', [
            new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_APPEND,
                name: 'wp-content/uploads/public/hero.jpg',
                version: $version,
                blockIndexes: [4],
                blockSize: 4096,
            ),
        ]);

        $t->same(null, $router->incomingFromEncryptedPeer($privateProgress));
        $t->same(null, $router->receiveIncoming($tracker, 'untrusted-peer', $privateProgress));
        $t->same([], $tracker->remoteDownloadProgressEvents());
        $t->same([], $tracker->remoteBlockCounts('untrusted-peer', 'wordpress-private-media'));

        $t->same($publicProgress, $router->incomingFromEncryptedPeer($publicProgress));
        $event = $router->receiveIncoming($tracker, 'trusted-peer', $publicProgress);
        $t->same([
            'device' => 'trusted-peer',
            'folder' => 'wordpress-public-media',
            'state' => ['wp-content/uploads/public/hero.jpg' => 1],
        ], $event);
        $t->same(4096, $tracker->bytesDownloaded('trusted-peer', 'wordpress-public-media'));
    },
    'maps encrypted file info consistency for ignored symlink metadata' => static function (TestRunner $t): void {
        $folderKey = str_repeat("\0", EncryptionKey::KEY_SIZE);
        $symlink = new FileInfo(
            name: 'wp-content/uploads/current',
            type: FileInfo::TYPE_SYMLINK,
            localFlags: FileInfo::FLAG_LOCAL_IGNORED,
            symlinkTarget: '2026',
            sequence: 81,
        );

        $encrypted = ReceiveEncrypted::encryptFileInfo($symlink, $folderKey, str_repeat("\3", ReceiveEncrypted::NONCE_SIZE));
        ProtocolValidation::checkFileInfoConsistency($encrypted);

        $t->same(FileInfo::TYPE_DIRECTORY, $encrypted->type);
        $t->same([], $encrypted->blocks);
        $t->same(0, $encrypted->size);
        $t->same(FileInfo::FLAG_LOCAL_REMOTE_INVALID, $encrypted->localFlags);

        $decrypted = ReceiveEncrypted::decryptFileInfo($encrypted, $folderKey);
        $t->same(FileInfo::TYPE_SYMLINK, $decrypted->type);
        $t->same('2026', $decrypted->symlinkTarget);
        $t->same(81, $decrypted->sequence);
        $t->same(FileInfo::FLAG_LOCAL_REMOTE_INVALID, $decrypted->localFlags);
    },
];

function syncthing_receive_encrypted_request_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-recvenc-request-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary test root');
    }

    return $root;
}

function syncthing_receive_encrypted_request_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write test file');
    }
}

function syncthing_receive_encrypted_request_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
