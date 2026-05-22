<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class EncryptionKey
{
    public const KEY_SIZE = 32;
    public const SCRYPT_N = 32768;
    public const SCRYPT_R = 8;
    public const SCRYPT_P = 1;

    private const BLOCK_SIZE = 16;
    private const UINT32_MASK = 0xffffffff;

    /** @var array<string, string> */
    private static array $folderKeyCache = [];

    public static function passwordToken(string $folderId, string $password): string
    {
        return self::encryptDeterministic(
            self::knownBytes($folderId),
            self::folderKeyFromPassword($folderId, $password),
        );
    }

    public static function passwordTokenHex(string $folderId, string $password): string
    {
        return bin2hex(self::passwordToken($folderId, $password));
    }

    public static function folderKeyFromPassword(string $folderId, string $password): string
    {
        $cacheKey = $folderId . "\0" . $password;
        if (isset(self::$folderKeyCache[$cacheKey])) {
            return self::$folderKeyCache[$cacheKey];
        }

        $key = self::scrypt(
            password: $password,
            salt: self::knownBytes($folderId),
            n: self::SCRYPT_N,
            r: self::SCRYPT_R,
            p: self::SCRYPT_P,
            length: self::KEY_SIZE,
        );

        return self::$folderKeyCache[$cacheKey] = $key;
    }

    public static function knownBytes(string $folderId): string
    {
        return 'syncthing' . $folderId;
    }

    public static function encryptDeterministic(string $data, string $key, ?string $additionalData = null): string
    {
        self::assertAesSivKey($key);
        $associatedData = $additionalData === null ? [''] : [$additionalData, ''];

        $iv = self::s2v($associatedData, $data, substr($key, 0, intdiv(strlen($key), 2)));
        $counter = self::zeroIvBits($iv);
        $ciphertext = self::aesCtr(substr($key, intdiv(strlen($key), 2)), $counter, $data);

        return $iv . $ciphertext;
    }

    public static function decryptDeterministic(string $data, string $key, ?string $additionalData = null): string
    {
        self::assertAesSivKey($key);
        if (strlen($data) < self::BLOCK_SIZE) {
            throw new \LengthException('AES-SIV ciphertext must include a 16-byte SIV');
        }

        $iv = substr($data, 0, self::BLOCK_SIZE);
        $ciphertext = substr($data, self::BLOCK_SIZE);
        $macKey = substr($key, 0, intdiv(strlen($key), 2));
        $plaintext = self::aesCtr(substr($key, intdiv(strlen($key), 2)), self::zeroIvBits($iv), $ciphertext);
        $associatedData = $additionalData === null ? [''] : [$additionalData, ''];
        $expectedIv = self::s2v($associatedData, $plaintext, $macKey);

        if (!hash_equals($expectedIv, $iv)) {
            throw new \RuntimeException('AES-SIV authentication failed');
        }

        return $plaintext;
    }

    public static function fileKey(string $filename, string $folderKey): string
    {
        self::assertFolderKey($folderKey);

        return self::hkdfSha256($folderKey . $filename, 'syncthing', '', self::KEY_SIZE);
    }

    /**
     * @param list<string> $associatedData
     */
    private static function s2v(array $associatedData, string $plaintext, string $macKey): string
    {
        $d = self::aesCmac($macKey, str_repeat("\0", self::BLOCK_SIZE));

        foreach ($associatedData as $item) {
            $d = self::xorBlock(self::doubleBlock($d), self::aesCmac($macKey, $item));
        }

        if (strlen($plaintext) >= self::BLOCK_SIZE) {
            $prefixLength = strlen($plaintext) - self::BLOCK_SIZE;
            $last = substr($plaintext, $prefixLength);
            return self::aesCmac($macKey, substr($plaintext, 0, $prefixLength) . self::xorBlock($last, $d));
        }

        return self::aesCmac($macKey, self::xorBlock(self::padBlock($plaintext), self::doubleBlock($d)));
    }

    private static function aesCmac(string $key, string $message): string
    {
        if (!in_array(strlen($key), [16, 32], true)) {
            throw new \LengthException('AES-CMAC key must be 16 or 32 bytes');
        }

        $l = self::aesBlockEncrypt($key, str_repeat("\0", self::BLOCK_SIZE));
        $k1 = self::doubleBlock($l);
        $k2 = self::doubleBlock($k1);

        $messageLength = strlen($message);
        $complete = $messageLength > 0 && ($messageLength % self::BLOCK_SIZE) === 0;
        $blockCount = $complete
            ? intdiv($messageLength, self::BLOCK_SIZE)
            : intdiv($messageLength, self::BLOCK_SIZE) + 1;

        $lastStart = ($blockCount - 1) * self::BLOCK_SIZE;
        $last = $complete
            ? self::xorBlock(substr($message, $lastStart, self::BLOCK_SIZE), $k1)
            : self::xorBlock(self::padBlock(substr($message, $lastStart)), $k2);

        $x = str_repeat("\0", self::BLOCK_SIZE);
        for ($i = 0; $i < $blockCount - 1; $i++) {
            $block = substr($message, $i * self::BLOCK_SIZE, self::BLOCK_SIZE);
            $x = self::aesBlockEncrypt($key, self::xorBlock($x, $block));
        }

        return self::aesBlockEncrypt($key, self::xorBlock($x, $last));
    }

    private static function aesBlockEncrypt(string $key, string $block): string
    {
        if (strlen($block) !== self::BLOCK_SIZE) {
            throw new \LengthException('AES block must be 16 bytes');
        }

        $cipher = strlen($key) === 16 ? 'aes-128-ecb' : 'aes-256-ecb';
        $encrypted = openssl_encrypt($block, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if ($encrypted === false || strlen($encrypted) !== self::BLOCK_SIZE) {
            throw new \RuntimeException('AES block encryption failed');
        }

        return $encrypted;
    }

    private static function aesCtr(string $key, string $iv, string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $cipher = strlen($key) === 16 ? 'aes-128-ctr' : 'aes-256-ctr';
        $encrypted = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('AES-CTR encryption failed');
        }

        return $encrypted;
    }

    private static function doubleBlock(string $block): string
    {
        if (strlen($block) !== self::BLOCK_SIZE) {
            throw new \LengthException('AES block must be 16 bytes');
        }

        $carry = 0;
        $out = '';
        for ($i = self::BLOCK_SIZE - 1; $i >= 0; $i--) {
            $byte = ord($block[$i]);
            $out = chr((($byte << 1) & 0xff) | $carry) . $out;
            $carry = $byte >> 7;
        }

        if ($carry !== 0) {
            $out[self::BLOCK_SIZE - 1] = chr(ord($out[self::BLOCK_SIZE - 1]) ^ 0x87);
        }

        return $out;
    }

    private static function padBlock(string $block): string
    {
        if (strlen($block) >= self::BLOCK_SIZE) {
            throw new \LengthException('Only partial blocks can be padded');
        }

        return $block . "\x80" . str_repeat("\0", self::BLOCK_SIZE - strlen($block) - 1);
    }

    private static function xorBlock(string $left, string $right): string
    {
        if (strlen($left) !== strlen($right)) {
            throw new \LengthException('XOR inputs must have the same length');
        }

        return $left ^ $right;
    }

    private static function zeroIvBits(string $iv): string
    {
        if (strlen($iv) !== self::BLOCK_SIZE) {
            throw new \LengthException('SIV must be 16 bytes');
        }

        $iv[8] = chr(ord($iv[8]) & 0x7f);
        $iv[12] = chr(ord($iv[12]) & 0x7f);

        return $iv;
    }

    private static function assertAesSivKey(string $key): void
    {
        if (!in_array(strlen($key), [32, 64], true)) {
            throw new \LengthException('AES-SIV key must be 32 or 64 bytes');
        }
    }

    private static function assertFolderKey(string $key): void
    {
        if (strlen($key) !== self::KEY_SIZE) {
            throw new \LengthException('Syncthing folder keys must be 32 bytes');
        }
    }

    private static function hkdfSha256(string $inputKeyMaterial, string $salt, string $info, int $length): string
    {
        if ($length <= 0) {
            throw new \InvalidArgumentException('HKDF output length must be positive');
        }

        $prk = hash_hmac('sha256', $inputKeyMaterial, $salt, true);
        $okm = '';
        $previous = '';
        $counter = 1;

        while (strlen($okm) < $length) {
            if ($counter > 255) {
                throw new \LengthException('HKDF output length is too large');
            }
            $previous = hash_hmac('sha256', $previous . $info . chr($counter), $prk, true);
            $okm .= $previous;
            $counter++;
        }

        return substr($okm, 0, $length);
    }

    private static function scrypt(string $password, string $salt, int $n, int $r, int $p, int $length): string
    {
        if ($n <= 1 || ($n & ($n - 1)) !== 0) {
            throw new \InvalidArgumentException('scrypt N must be a power of two greater than one');
        }
        if ($r <= 0 || $p <= 0 || $length <= 0) {
            throw new \InvalidArgumentException('scrypt parameters must be positive');
        }

        $blockLength = 128 * $r;
        $b = hash_pbkdf2('sha256', $password, $salt, 1, $p * $blockLength, true);

        for ($i = 0; $i < $p; $i++) {
            $mixed = self::sMix(substr($b, $i * $blockLength, $blockLength), $r, $n);
            $b = substr_replace($b, $mixed, $i * $blockLength, $blockLength);
        }

        return hash_pbkdf2('sha256', $password, $b, 1, $length, true);
    }

    private static function sMix(string $block, int $r, int $n): string
    {
        $wordsPerBlock = 32 * $r;
        $x = self::bytesToWords($block);
        $v = [];

        for ($i = 0; $i < $n; $i++) {
            $v[$i] = self::wordsToBytes($x);
            $x = self::blockMix($x, $r);
        }

        for ($i = 0; $i < $n; $i++) {
            $j = $x[(2 * $r - 1) * 16] & ($n - 1);
            $vj = self::bytesToWords($v[$j]);
            for ($k = 0; $k < $wordsPerBlock; $k++) {
                $x[$k] = ($x[$k] ^ $vj[$k]) & self::UINT32_MASK;
            }
            $x = self::blockMix($x, $r);
        }

        return self::wordsToBytes($x);
    }

    /**
     * @param list<int> $in
     * @return list<int>
     */
    private static function blockMix(array $in, int $r): array
    {
        $tmp = [];
        $lastOffset = (2 * $r - 1) * 16;
        for ($i = 0; $i < 16; $i++) {
            $tmp[$i] = $in[$lastOffset + $i];
        }

        $out = array_fill(0, 32 * $r, 0);
        for ($i = 0; $i < 2 * $r; $i += 2) {
            $tmp = self::salsaXor($tmp, $in, $i * 16);
            $outOffset = $i * 8;
            for ($j = 0; $j < 16; $j++) {
                $out[$outOffset + $j] = $tmp[$j];
            }

            $tmp = self::salsaXor($tmp, $in, $i * 16 + 16);
            $outOffset = $r * 16 + $i * 8;
            for ($j = 0; $j < 16; $j++) {
                $out[$outOffset + $j] = $tmp[$j];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $tmp
     * @param list<int> $in
     * @return list<int>
     */
    private static function salsaXor(array $tmp, array $in, int $offset): array
    {
        $mask = self::UINT32_MASK;

        $w0 = ($tmp[0] ^ $in[$offset]) & $mask;
        $w1 = ($tmp[1] ^ $in[$offset + 1]) & $mask;
        $w2 = ($tmp[2] ^ $in[$offset + 2]) & $mask;
        $w3 = ($tmp[3] ^ $in[$offset + 3]) & $mask;
        $w4 = ($tmp[4] ^ $in[$offset + 4]) & $mask;
        $w5 = ($tmp[5] ^ $in[$offset + 5]) & $mask;
        $w6 = ($tmp[6] ^ $in[$offset + 6]) & $mask;
        $w7 = ($tmp[7] ^ $in[$offset + 7]) & $mask;
        $w8 = ($tmp[8] ^ $in[$offset + 8]) & $mask;
        $w9 = ($tmp[9] ^ $in[$offset + 9]) & $mask;
        $w10 = ($tmp[10] ^ $in[$offset + 10]) & $mask;
        $w11 = ($tmp[11] ^ $in[$offset + 11]) & $mask;
        $w12 = ($tmp[12] ^ $in[$offset + 12]) & $mask;
        $w13 = ($tmp[13] ^ $in[$offset + 13]) & $mask;
        $w14 = ($tmp[14] ^ $in[$offset + 14]) & $mask;
        $w15 = ($tmp[15] ^ $in[$offset + 15]) & $mask;

        $x0 = $w0;
        $x1 = $w1;
        $x2 = $w2;
        $x3 = $w3;
        $x4 = $w4;
        $x5 = $w5;
        $x6 = $w6;
        $x7 = $w7;
        $x8 = $w8;
        $x9 = $w9;
        $x10 = $w10;
        $x11 = $w11;
        $x12 = $w12;
        $x13 = $w13;
        $x14 = $w14;
        $x15 = $w15;

        for ($round = 0; $round < 8; $round += 2) {
            $t = ($x0 + $x12) & $mask;
            $x4 = ($x4 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x4 + $x0) & $mask;
            $x8 = ($x8 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x8 + $x4) & $mask;
            $x12 = ($x12 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x12 + $x8) & $mask;
            $x0 = ($x0 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x5 + $x1) & $mask;
            $x9 = ($x9 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x9 + $x5) & $mask;
            $x13 = ($x13 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x13 + $x9) & $mask;
            $x1 = ($x1 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x1 + $x13) & $mask;
            $x5 = ($x5 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x10 + $x6) & $mask;
            $x14 = ($x14 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x14 + $x10) & $mask;
            $x2 = ($x2 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x2 + $x14) & $mask;
            $x6 = ($x6 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x6 + $x2) & $mask;
            $x10 = ($x10 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x15 + $x11) & $mask;
            $x3 = ($x3 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x3 + $x15) & $mask;
            $x7 = ($x7 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x7 + $x3) & $mask;
            $x11 = ($x11 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x11 + $x7) & $mask;
            $x15 = ($x15 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x0 + $x3) & $mask;
            $x1 = ($x1 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x1 + $x0) & $mask;
            $x2 = ($x2 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x2 + $x1) & $mask;
            $x3 = ($x3 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x3 + $x2) & $mask;
            $x0 = ($x0 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x5 + $x4) & $mask;
            $x6 = ($x6 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x6 + $x5) & $mask;
            $x7 = ($x7 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x7 + $x6) & $mask;
            $x4 = ($x4 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x4 + $x7) & $mask;
            $x5 = ($x5 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x10 + $x9) & $mask;
            $x11 = ($x11 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x11 + $x10) & $mask;
            $x8 = ($x8 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x8 + $x11) & $mask;
            $x9 = ($x9 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x9 + $x8) & $mask;
            $x10 = ($x10 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;

            $t = ($x15 + $x14) & $mask;
            $x12 = ($x12 ^ ((($t << 7) | ($t >> 25)) & $mask)) & $mask;
            $t = ($x12 + $x15) & $mask;
            $x13 = ($x13 ^ ((($t << 9) | ($t >> 23)) & $mask)) & $mask;
            $t = ($x13 + $x12) & $mask;
            $x14 = ($x14 ^ ((($t << 13) | ($t >> 19)) & $mask)) & $mask;
            $t = ($x14 + $x13) & $mask;
            $x15 = ($x15 ^ ((($t << 18) | ($t >> 14)) & $mask)) & $mask;
        }

        return [
            ($x0 + $w0) & $mask,
            ($x1 + $w1) & $mask,
            ($x2 + $w2) & $mask,
            ($x3 + $w3) & $mask,
            ($x4 + $w4) & $mask,
            ($x5 + $w5) & $mask,
            ($x6 + $w6) & $mask,
            ($x7 + $w7) & $mask,
            ($x8 + $w8) & $mask,
            ($x9 + $w9) & $mask,
            ($x10 + $w10) & $mask,
            ($x11 + $w11) & $mask,
            ($x12 + $w12) & $mask,
            ($x13 + $w13) & $mask,
            ($x14 + $w14) & $mask,
            ($x15 + $w15) & $mask,
        ];
    }

    /**
     * @return list<int>
     */
    private static function bytesToWords(string $bytes): array
    {
        if ((strlen($bytes) % 4) !== 0) {
            throw new \LengthException('scrypt byte blocks must be uint32-aligned');
        }

        return array_values(unpack('V*', $bytes));
    }

    /**
     * @param list<int> $words
     */
    private static function wordsToBytes(array $words): string
    {
        return pack('V*', ...$words);
    }
}
