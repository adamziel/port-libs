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

    private static function assertBase32HexToken(string $token): void
    {
        if ($token === '' || !preg_match('/^[0-9A-V]+$/', $token)) {
            throw new \InvalidArgumentException('Encrypted name token must be unpadded base32-hex');
        }
    }
}
