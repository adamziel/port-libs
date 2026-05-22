<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ReceiveEncrypted
{
    public const NONCE_SIZE = 24;
    public const TAG_SIZE = 16;
    public const BLOCK_OVERHEAD = self::NONCE_SIZE + self::TAG_SIZE;
    public const MIN_PADDED_SIZE = 1024;
    public const MAX_PATH_COMPONENT = 200;
    public const ENCRYPTED_DIR_EXTENSION = '.syncthing-enc';

    public static function passwordTokenHex(string $folderId, string $password): string
    {
        return EncryptionKey::passwordTokenHex($folderId, $password);
    }

    public static function fileKey(string $plainName, string $folderKey): string
    {
        return EncryptionKey::fileKey($plainName, $folderKey);
    }

    public static function encryptName(string $plainName, string $folderKey): string
    {
        return self::slashifyBase32Hex(self::base32HexEncode(EncryptionKey::encryptDeterministic($plainName, $folderKey)));
    }

    public static function decryptName(string $encryptedName, string $folderKey): string
    {
        return EncryptionKey::decryptDeterministic(
            self::base32HexDecode(self::deslashifyBase32HexPath($encryptedName)),
            $folderKey,
        );
    }

    public static function encryptBlockHashHex(string $plainHashHex, int $offset, string $fileKey): string
    {
        self::assertHexBytes($plainHashHex, 'block hash');

        return bin2hex(EncryptionKey::encryptDeterministic(
            $plainHashHex === '' ? '' : hex2bin($plainHashHex),
            $fileKey,
            self::offsetAdditionalData($offset),
        ));
    }

    public static function decryptBlockHashHex(string $encryptedHashTokenHex, int $offset, string $fileKey): string
    {
        self::assertHexBytes($encryptedHashTokenHex, 'encrypted block hash token');
        if ($encryptedHashTokenHex === '') {
            return '';
        }

        $token = hex2bin($encryptedHashTokenHex);
        try {
            return bin2hex(EncryptionKey::decryptDeterministic($token, $fileKey, self::offsetAdditionalData($offset)));
        } catch (\RuntimeException) {
            return bin2hex(EncryptionKey::decryptDeterministic($token, $fileKey));
        }
    }

    public static function encryptBytes(string $data, string $fileKey, ?string $nonce = null): string
    {
        self::assertFileKey($fileKey);
        self::assertSodiumAvailable();

        $nonce ??= random_bytes(self::NONCE_SIZE);
        if (strlen($nonce) !== self::NONCE_SIZE) {
            throw new \LengthException('XChaCha20-Poly1305 nonce must be 24 bytes');
        }

        return $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, '', $nonce, $fileKey);
    }

    public static function decryptBytes(string $encryptedBytes, string $fileKey): string
    {
        self::assertFileKey($fileKey);
        self::assertSodiumAvailable();

        if (strlen($encryptedBytes) < self::BLOCK_OVERHEAD) {
            throw new \LengthException('Encrypted data is too short');
        }

        $nonce = substr($encryptedBytes, 0, self::NONCE_SIZE);
        $ciphertext = substr($encryptedBytes, self::NONCE_SIZE);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $fileKey);
        if ($plaintext === false) {
            throw new \RuntimeException('XChaCha20-Poly1305 authentication failed');
        }

        return $plaintext;
    }

    public static function encryptFileInfo(FileInfo $file, string $folderKey, ?string $nonce = null): FileInfo
    {
        $fileKey = self::fileKey($file->name, $folderKey);
        $encryptedPayload = self::encryptBytes(BepWire::encodeFileInfoPayload($file), $fileKey, $nonce);
        $blocks = [];
        $encryptedOffset = 0;

        foreach ($file->blocks as $block) {
            $paddedSize = max($block->size, self::MIN_PADDED_SIZE);
            $encryptedSize = $paddedSize + self::BLOCK_OVERHEAD;
            $blocks[] = new Block(
                offset: $encryptedOffset,
                size: $encryptedSize,
                hashHex: self::encryptBlockHashHex($block->hashHex, $block->offset, $fileKey),
            );
            $encryptedOffset += $encryptedSize;
        }

        $encryptedType = $file->type === FileInfo::TYPE_FILE ? FileInfo::TYPE_FILE : FileInfo::TYPE_DIRECTORY;
        $fakeVersionValue = array_sum($file->version->toArray());

        return new FileInfo(
            name: self::encryptName($file->name, $folderKey),
            modifiedS: 1234567890,
            version: VersionVector::fromCounters([1 => $fakeVersionValue]),
            deleted: $file->deleted,
            localFlags: $file->isInvalid() ? FileInfo::FLAG_LOCAL_REMOTE_INVALID : 0,
            size: $encryptedType === FileInfo::TYPE_FILE ? $encryptedOffset : 0,
            type: $encryptedType,
            permissions: 0644,
            rawBlockSize: $encryptedType === FileInfo::TYPE_FILE ? $file->blockSize() + self::BLOCK_OVERHEAD : 0,
            sequence: $file->sequence,
            blocks: $encryptedType === FileInfo::TYPE_FILE ? $blocks : [],
            encryptedPayload: $encryptedPayload,
        );
    }

    public static function decryptFileInfo(FileInfo $encryptedFile, string $folderKey): FileInfo
    {
        $plainName = self::decryptName($encryptedFile->name, $folderKey);
        $fileKey = self::fileKey($plainName, $folderKey);
        $payload = self::decryptBytes($encryptedFile->encryptedPayload, $fileKey);

        return BepWire::decodeFileInfoPayload($payload)->withSequence($encryptedFile->sequence);
    }

    /**
     * @param list<FileInfo> $files
     *
     * @return list<FileInfo>
     */
    public static function encryptFileInfos(
        array $files,
        string $folderKey,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): array {
        $encrypted = [];
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }

            $encrypted[] = self::encryptFileInfo(
                $file->withName(ProtocolValidation::normalizeWireName($file->name, $directorySeparator)),
                $folderKey,
            );
        }

        return $encrypted;
    }

    /**
     * @param list<FileInfo> $files
     *
     * @return list<FileInfo>
     */
    public static function decryptFileInfos(array $files, string $folderKey): array
    {
        $decrypted = [];
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }

            $decrypted[] = self::decryptFileInfo($file, $folderKey);
        }

        return $decrypted;
    }

    public static function encryptIndex(
        Index $index,
        string $folderKey,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): Index {
        return new Index(
            folder: $index->folder,
            files: self::encryptFileInfos($index->files, $folderKey, $directorySeparator),
            lastSequence: $index->lastSequence,
        );
    }

    public static function decryptIndex(Index $index, string $folderKey): Index
    {
        return new Index(
            folder: $index->folder,
            files: self::decryptFileInfos($index->files, $folderKey),
            lastSequence: $index->lastSequence,
        );
    }

    public static function encryptIndexUpdate(
        IndexUpdate $indexUpdate,
        string $folderKey,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): IndexUpdate {
        return new IndexUpdate(
            folder: $indexUpdate->folder,
            files: self::encryptFileInfos($indexUpdate->files, $folderKey, $directorySeparator),
            lastSequence: $indexUpdate->lastSequence,
            prevSequence: $indexUpdate->prevSequence,
        );
    }

    public static function decryptIndexUpdate(IndexUpdate $indexUpdate, string $folderKey): IndexUpdate
    {
        return new IndexUpdate(
            folder: $indexUpdate->folder,
            files: self::decryptFileInfos($indexUpdate->files, $folderKey),
            lastSequence: $indexUpdate->lastSequence,
            prevSequence: $indexUpdate->prevSequence,
        );
    }

    public static function encryptRequestForEncryptedPeer(Request $request, string $folderKey): Request
    {
        $fileKey = self::fileKey($request->name, $folderKey);

        return self::requestToEncryptedPeer(
            $request,
            self::encryptName($request->name, $folderKey),
            self::encryptBlockHashHex($request->hashHex, $request->offset, $fileKey),
        );
    }

    public static function decryptRequestFromEncryptedPeer(Request $request, string $folderKey): Request
    {
        $plainName = self::decryptName($request->name, $folderKey);
        $realOffset = $request->offset - ($request->blockNo * self::BLOCK_OVERHEAD);
        if ($realOffset < 0) {
            throw new \InvalidArgumentException('encrypted request offset is shorter than block overhead');
        }

        $plainHashHex = '';
        if ($request->hashHex !== '') {
            $plainHashHex = self::decryptBlockHashHex(
                $request->hashHex,
                $realOffset,
                self::fileKey($plainName, $folderKey),
            );
        }

        return self::requestFromEncryptedPeer($request, $plainName, $plainHashHex);
    }

    public static function serveEncryptedRequestFromPeer(
        RequestServer $server,
        string $deviceId,
        Request $encryptedRequest,
        string $folderKey,
        ?string $paddingBytes = null,
        ?string $nonce = null,
    ): RequestServingResult {
        try {
            $plainRequest = self::decryptRequestFromEncryptedPeer($encryptedRequest, $folderKey);
        } catch (\InvalidArgumentException | \LengthException | \RuntimeException) {
            return new RequestServingResult(
                new Response($encryptedRequest->id, '', Response::CODE_GENERIC),
                RequestServingResult::SOURCE_NONE,
                'decrypting encrypted request failed',
            );
        }

        $served = $server->serve($deviceId, $plainRequest);
        $fileKey = self::fileKey($plainRequest->name, $folderKey);

        return new RequestServingResult(
            self::encryptResponseForEncryptedPeer($served->response, $fileKey, $paddingBytes, $nonce),
            $served->source,
            $served->reason,
        );
    }

    public static function requestToEncryptedPeer(
        Request $request,
        string $encryptedName,
        string $encryptedHashTokenHex = '',
    ): Request {
        self::deslashifyBase32HexPath($encryptedName);

        $size = max($request->size, self::MIN_PADDED_SIZE) + self::BLOCK_OVERHEAD;
        $offset = $request->offset + ($request->blockNo * self::BLOCK_OVERHEAD);

        return new Request(
            id: $request->id,
            folder: $request->folder,
            name: $encryptedName,
            offset: $offset,
            size: $size,
            hashHex: $encryptedHashTokenHex,
            fromTemporary: false,
            blockNo: $request->blockNo,
        );
    }

    public static function requestFromEncryptedPeer(
        Request $request,
        string $plainName,
        string $plainHashHex = '',
    ): Request {
        if ($request->size < self::MIN_PADDED_SIZE) {
            throw new \InvalidArgumentException('short request');
        }

        return new Request(
            id: $request->id,
            folder: $request->folder,
            name: $plainName,
            offset: $request->offset - ($request->blockNo * self::BLOCK_OVERHEAD),
            size: $request->size - self::BLOCK_OVERHEAD,
            hashHex: $plainHashHex,
            fromTemporary: $request->fromTemporary,
            blockNo: $request->blockNo,
        );
    }

    public static function encryptResponseForEncryptedPeer(
        Response $response,
        string $fileKey,
        ?string $paddingBytes = null,
        ?string $nonce = null,
    ): Response {
        if ($response->code !== Response::CODE_NO_ERROR) {
            return $response;
        }

        $data = $response->data;
        $paddingLength = self::MIN_PADDED_SIZE - strlen($data);
        if ($paddingLength > 0) {
            if ($paddingBytes === null) {
                $paddingBytes = random_bytes($paddingLength);
            }
            if (strlen($paddingBytes) < $paddingLength) {
                throw new \LengthException('Not enough response padding bytes supplied');
            }
            $data .= substr($paddingBytes, 0, $paddingLength);
        }

        return new Response(
            id: $response->id,
            data: self::encryptBytes($data, $fileKey, $nonce),
            code: $response->code,
        );
    }

    public static function decryptResponseFromEncryptedPeer(
        Response $response,
        string $fileKey,
        int $plainSize,
    ): Response {
        if ($plainSize < 0) {
            throw new \InvalidArgumentException('Plain response size must not be negative');
        }
        if ($response->code !== Response::CODE_NO_ERROR) {
            return $response;
        }

        $data = self::decryptBytes($response->data, $fileKey);
        if (strlen($data) < $plainSize) {
            throw new \LengthException('Decrypted response is shorter than the requested plaintext size');
        }

        return new Response(
            id: $response->id,
            data: substr($data, 0, $plainSize),
            code: $response->code,
        );
    }

    public static function slashifyBase32Hex(string $base32Hex): string
    {
        self::assertBase32HexToken($base32Hex);
        if (strlen($base32Hex) < 3) {
            throw new \LengthException('Encrypted name token must have at least three characters');
        }

        $components = [$base32Hex[0] . self::ENCRYPTED_DIR_EXTENSION];
        $base32Hex = substr($base32Hex, 1);
        $components[] = substr($base32Hex, 0, 2);
        $base32Hex = substr($base32Hex, 2);

        while (strlen($base32Hex) > self::MAX_PATH_COMPONENT) {
            $components[] = substr($base32Hex, 0, self::MAX_PATH_COMPONENT);
            $base32Hex = substr($base32Hex, self::MAX_PATH_COMPONENT);
        }

        if ($base32Hex !== '') {
            $components[] = $base32Hex;
        }

        return implode('/', $components);
    }

    public static function deslashifyBase32HexPath(string $path): string
    {
        if ($path === '' || strlen($path) < 2 || !str_starts_with(substr($path, 1), self::ENCRYPTED_DIR_EXTENSION)) {
            throw new \InvalidArgumentException(sprintf('invalid encrypted path: "%s"', $path));
        }

        $components = explode('/', $path);
        foreach ($components as $component) {
            if ($component === '') {
                throw new \InvalidArgumentException(sprintf('invalid encrypted path: "%s"', $path));
            }
        }

        $token = $path[0] . substr($path, 1 + strlen(self::ENCRYPTED_DIR_EXTENSION));
        $token = str_replace('/', '', $token);
        self::assertBase32HexToken($token);

        return $token;
    }

    /**
     * @param list<string>|string $path
     */
    public static function isEncryptedParent(array|string $path): bool
    {
        $components = is_array($path) ? array_values($path) : explode('/', $path);
        $count = count($components);

        if ($count === 2 && strlen($components[1]) !== 2) {
            return false;
        }
        if ($count === 0 || $components[0] === '') {
            return false;
        }
        if (substr($components[0], 1) !== self::ENCRYPTED_DIR_EXTENSION) {
            return false;
        }
        if ($count < 2) {
            return true;
        }

        for ($index = 2; $index < $count; $index++) {
            if (strlen($components[$index]) !== self::MAX_PATH_COMPONENT) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{file:?FileInfo, syntheticParent:bool, removedEmptyParent:bool}
     */
    public static function receiveEncryptedScanUpdate(FileInfo $file, string $rootPath): array
    {
        if (!$file->isDirectory() || !self::isEncryptedParent($file->name)) {
            return [
                'file' => $file,
                'syntheticParent' => false,
                'removedEmptyParent' => false,
            ];
        }

        return [
            'file' => null,
            'syntheticParent' => true,
            'removedEmptyParent' => self::removeEmptySyntheticParentDirectory($rootPath, $file->name),
        ];
    }

    /**
     * @return array{parent:string, created:bool, scanAfterPull:bool}
     */
    public static function ensureReceiveEncryptedParentDirectory(string $rootPath, string $encryptedName): array
    {
        ProtocolValidation::checkFilename($encryptedName);
        self::deslashifyBase32HexPath($encryptedName);

        $parent = dirname($encryptedName);
        if ($parent === '.' || $parent === '' || !self::isEncryptedParent($parent)) {
            throw new \InvalidArgumentException('Encrypted file parent is not a synthetic parent directory');
        }

        $root = self::existingDirectoryRoot($rootPath);
        self::assertNoSymlinkInExistingPath($root, $parent);
        $path = self::absoluteRelativePath($root, $parent);
        $created = !is_dir($path);

        if ($created && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Failed to create receive-encrypted parent directory');
        }

        return [
            'parent' => $parent,
            'created' => $created,
            'scanAfterPull' => false,
        ];
    }

    public static function removeEmptySyntheticParentDirectory(string $rootPath, string $relativeName): bool
    {
        ProtocolValidation::checkFilename($relativeName);
        if (!self::isEncryptedParent($relativeName)) {
            throw new \InvalidArgumentException('Path is not a synthetic encrypted parent directory');
        }

        $root = self::existingDirectoryRoot($rootPath);
        $path = self::absoluteRelativePath($root, $relativeName);
        if (is_link($path) || !is_dir($path)) {
            return false;
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        if ($iterator->valid()) {
            return false;
        }

        return rmdir($path);
    }

    public static function encryptionTrailer(FileInfo $file, string $directorySeparator = DIRECTORY_SEPARATOR): string
    {
        $wireFile = $file->withName(ProtocolValidation::normalizeWireName($file->name, $directorySeparator));
        $payload = BepWire::encodeFileInfoPayload($wireFile);

        return $payload . pack('N', strlen($payload));
    }

    public static function appendEncryptionTrailer(
        string $encryptedFileBytes,
        FileInfo $file,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): string {
        if (strlen($encryptedFileBytes) !== $file->size) {
            throw new \InvalidArgumentException('Encrypted file byte length must match FileInfo size before appending trailer');
        }

        return $encryptedFileBytes . self::encryptionTrailer($file, $directorySeparator);
    }

    /**
     * @return array{bytes:string, file:FileInfo, trailerSize:int}
     */
    public static function finalizeEncryptedFile(
        string $encryptedFileBytes,
        FileInfo $encryptedFile,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): array {
        if ($encryptedFile->encryptedPayload === '') {
            throw new \InvalidArgumentException('Encrypted FileInfo metadata is required before finalization');
        }

        $bytes = self::appendEncryptionTrailer($encryptedFileBytes, $encryptedFile, $directorySeparator);
        $trailerSize = strlen($bytes) - strlen($encryptedFileBytes);

        return [
            'bytes' => $bytes,
            'file' => $encryptedFile->withSize($encryptedFile->size + $trailerSize),
            'trailerSize' => $trailerSize,
        ];
    }

    public static function prepareFinalizedFileInfoForIndex(FileInfo $finalizedFile, int $trailerSize): FileInfo
    {
        if ($trailerSize < 0) {
            throw new \InvalidArgumentException('Encrypted file trailer size must not be negative');
        }
        if ($trailerSize > $finalizedFile->size) {
            throw new \LengthException('Encrypted file trailer size exceeds finalized file size');
        }

        return $finalizedFile->withSize($finalizedFile->size - $trailerSize);
    }

    /**
     * @return array{data:string, file:FileInfo, trailerSize:int}
     */
    public static function extractEncryptionTrailer(string $encryptedFileBytes): array
    {
        if (strlen($encryptedFileBytes) < 4) {
            throw new \LengthException('Encrypted file is too short to contain a trailer');
        }

        $payloadSize = unpack('N', substr($encryptedFileBytes, -4))[1];
        $trailerSize = $payloadSize + 4;
        if ($payloadSize <= 0 || $trailerSize > strlen($encryptedFileBytes)) {
            throw new \LengthException('Encrypted file trailer length is invalid');
        }

        $payload = substr($encryptedFileBytes, -$trailerSize, $payloadSize);

        return [
            'data' => substr($encryptedFileBytes, 0, -$trailerSize),
            'file' => BepWire::decodeFileInfoPayload($payload),
            'trailerSize' => $trailerSize,
        ];
    }

    /**
     * @return array{
     *     plaintext:string,
     *     encryptedData:string,
     *     encryptedFile:FileInfo,
     *     plainFile:FileInfo,
     *     trailerSize:int
     * }
     */
    public static function verifyFinalizedEncryptedFile(string $finalizedBytes, string $folderKey): array
    {
        $extracted = self::extractEncryptionTrailer($finalizedBytes);
        $encryptedData = $extracted['data'];
        $encryptedFile = $extracted['file'];
        if ($encryptedFile->encryptedPayload === '') {
            throw new \UnexpectedValueException('Encrypted FileInfo trailer lacks encrypted metadata');
        }
        if (strlen($encryptedData) !== $encryptedFile->size) {
            throw new \LengthException('Encrypted data length does not match FileInfo size');
        }

        $plainFile = self::decryptFileInfo($encryptedFile, $folderKey);
        $plaintext = self::decryptAndVerifyFileBlocks($encryptedData, $encryptedFile, $plainFile, $folderKey);

        return [
            'plaintext' => $plaintext,
            'encryptedData' => $encryptedData,
            'encryptedFile' => $encryptedFile,
            'plainFile' => $plainFile,
            'trailerSize' => $extracted['trailerSize'],
        ];
    }

    private static function decryptAndVerifyFileBlocks(
        string $encryptedData,
        FileInfo $encryptedFile,
        FileInfo $plainFile,
        string $folderKey,
    ): string {
        if (count($encryptedFile->blocks) !== count($plainFile->blocks)) {
            throw new \UnexpectedValueException('Encrypted and plaintext block counts differ');
        }

        $fileKey = self::fileKey($plainFile->name, $folderKey);
        $plaintext = '';
        $lastIndex = count($plainFile->blocks) - 1;
        $blockList = new BlockList();

        foreach ($encryptedFile->blocks as $index => $encryptedBlock) {
            $plainBlock = $plainFile->blocks[$index];
            if ($encryptedBlock->offset < 0 || $encryptedBlock->size < 0) {
                throw new \UnexpectedValueException('Encrypted block geometry must not be negative');
            }
            if ($encryptedBlock->offset + $encryptedBlock->size > strlen($encryptedData)) {
                throw new \LengthException('Encrypted block extends beyond finalized data');
            }

            $decrypted = self::decryptBytes(
                substr($encryptedData, $encryptedBlock->offset, $encryptedBlock->size),
                $fileKey,
            );

            if ($index === $lastIndex && strlen($decrypted) > $plainBlock->size) {
                $decrypted = substr($decrypted, 0, $plainBlock->size);
            } elseif (strlen($decrypted) !== $plainBlock->size) {
                throw new \LengthException('Plaintext block size mismatch');
            }

            if (!$blockList->validateBytes($decrypted, $plainBlock->hashHex)) {
                throw new \RuntimeException('Plaintext block hash validation failed');
            }

            $plaintext .= $decrypted;
        }

        if (strlen($plaintext) !== $plainFile->size) {
            throw new \LengthException('Plaintext size does not match FileInfo size');
        }

        return $plaintext;
    }

    private static function assertBase32HexToken(string $token): void
    {
        if ($token === '' || !preg_match('/^[0-9A-V]+$/', $token)) {
            throw new \InvalidArgumentException('Encrypted name token must be unpadded base32-hex');
        }
    }

    private static function assertHexBytes(string $hex, string $label): void
    {
        if ($hex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $hex)) {
            throw new \InvalidArgumentException('Expected lowercase even-length hex for ' . $label);
        }
    }

    private static function assertFileKey(string $key): void
    {
        if (strlen($key) !== EncryptionKey::KEY_SIZE) {
            throw new \LengthException('Syncthing file keys must be 32 bytes');
        }
    }

    private static function existingDirectoryRoot(string $rootPath): string
    {
        $root = realpath($rootPath);
        if ($root === false || !is_dir($root)) {
            throw new \InvalidArgumentException('Receive-encrypted root path must be an existing directory');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private static function absoluteRelativePath(string $root, string $relativeName): string
    {
        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeName);
    }

    private static function assertNoSymlinkInExistingPath(string $root, string $relativeName): void
    {
        $path = $root;
        foreach (explode('/', $relativeName) as $component) {
            $path .= DIRECTORY_SEPARATOR . $component;
            if (is_link($path)) {
                throw new \RuntimeException('Receive-encrypted parent directory traverses a symlink');
            }
            if (file_exists($path) && !is_dir($path)) {
                throw new \RuntimeException('Receive-encrypted parent path is not a directory');
            }
        }
    }

    private static function assertSodiumAvailable(): void
    {
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new \RuntimeException('The sodium extension is required for XChaCha20-Poly1305 encryption');
        }
    }

    private static function offsetAdditionalData(int $offset): string
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Block hash offsets must be non-negative');
        }
        if ($offset > PHP_INT_MAX) {
            throw new \InvalidArgumentException('Block hash offset exceeds PHP integer range');
        }

        $high = intdiv($offset, 0x100000000);
        $low = $offset % 0x100000000;

        return pack('N2', $high, $low);
    }

    private static function base32HexEncode(string $bytes): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
        $buffer = 0;
        $bits = 0;
        $out = '';

        for ($i = 0, $length = strlen($bytes); $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $out .= $alphabet[($buffer >> ($bits - 5)) & 0x1f];
                $bits -= 5;
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 0x1f];
        }

        return $out;
    }

    private static function base32HexDecode(string $token): string
    {
        self::assertBase32HexToken($token);
        if (in_array(strlen($token) % 8, [1, 3, 6], true)) {
            throw new \InvalidArgumentException('Invalid unpadded base32-hex length');
        }

        $alphabet = array_flip(str_split('0123456789ABCDEFGHIJKLMNOPQRSTUV'));
        $buffer = 0;
        $bits = 0;
        $out = '';

        for ($i = 0, $length = strlen($token); $i < $length; $i++) {
            $buffer = ($buffer << 5) | $alphabet[$token[$i]];
            $bits += 5;
            if ($bits >= 8) {
                $out .= chr(($buffer >> ($bits - 8)) & 0xff);
                $bits -= 8;
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0 && $buffer !== 0) {
            throw new \InvalidArgumentException('Invalid unpadded base32-hex trailing bits');
        }

        return $out;
    }
}
