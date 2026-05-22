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

    private static function assertHexBytes(string $hex, string $label): void
    {
        if ($hex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $hex)) {
            throw new \InvalidArgumentException('Expected lowercase even-length hex for ' . $label);
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
