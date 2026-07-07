<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\AstNode;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    foreach (str_split($bytes, 4) as $chunk) {
        $length = strlen($chunk);
        if ($length < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $length === 4) {
            $encoded .= 'z';
            continue;
        }

        $digits = array_fill(0, 5, 0);
        for ($index = 4; $index >= 0; $index--) {
            $digits[$index] = $value % 85;
            $value = intdiv($value, 85);
        }

        $group = '';
        foreach ($digits as $digit) {
            $group .= chr($digit + 33);
        }
        $encoded .= substr($group, 0, $length + 1);
    }

    return $encoded . '~>';
};

$lzwEncode = static function (string $bytes, int $earlyChange = 1): string {
    if ($earlyChange !== 0 && $earlyChange !== 1) {
        throw new RuntimeException('Invalid LZW EarlyChange fixture value.');
    }

    $dictionary = [];
    for ($code = 0; $code < 256; $code++) {
        $dictionary[chr($code)] = $code;
    }

    $encoded = '';
    $buffer = 0;
    $bufferBits = 0;
    $nextCode = 258;
    $codeSize = 9;
    $writeCode = static function (int $code, int $width) use (&$encoded, &$buffer, &$bufferBits): void {
        for ($bit = $width - 1; $bit >= 0; $bit--) {
            $buffer = ($buffer << 1) | (($code >> $bit) & 1);
            $bufferBits++;
            if ($bufferBits === 8) {
                $encoded .= chr($buffer);
                $buffer = 0;
                $bufferBits = 0;
            }
        }
    };
    $maybeGrowCodeSize = static function () use (&$codeSize, &$nextCode, $earlyChange): void {
        if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
            $codeSize++;
        }
    };

    $writeCode(256, $codeSize);
    $word = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
        $char = $bytes[$offset];
        if ($word === '') {
            $word = $char;
            continue;
        }

        $candidate = $word . $char;
        if (isset($dictionary[$candidate])) {
            $word = $candidate;
            continue;
        }

        $writeCode($dictionary[$word], $codeSize);
        if ($nextCode < 4096) {
            $dictionary[$candidate] = $nextCode;
            $nextCode++;
            $maybeGrowCodeSize();
        }
        $word = $char;
    }

    if ($word !== '') {
        $writeCode($dictionary[$word], $codeSize);
    }
    $writeCode(257, $codeSize);

    if ($bufferBits > 0) {
        $encoded .= chr($buffer << (8 - $bufferBits));
    }

    return $encoded;
};

$standardR2EncryptedPdfWithContent = static function (string $content, int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };

    $ownerKey = substr(md5($passwordPadding, true), 0, 5);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    $fileId = hex2bin('00112233445566778899AABBCCDDEEFF');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKey = substr(md5($passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId, true), 0, 5);
    $userValue = $rc4($passwordPadding, $fileKey);
    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 10);
    $encryptedContent = $rc4($content, $contentObjectKey);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 1 /R 2 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} >>\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR3EncryptedPdfWithContent = static function (string $content, int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };

    $keyLength = 16;
    $ownerHash = md5($passwordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('102132435465768798A9BACBDCEDFE0F');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyHash = md5($passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 16);
    $encryptedContent = $rc4($content, $contentObjectKey);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 2 /R 3 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} >>\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR4V2EncryptedPdfWithContent = static function (string $content, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };

    $keyLength = 16;
    $ownerHash = md5($passwordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('2233445566778899AABBCCDDEEFF0011');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyInput = $passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId . "\xFF\xFF\xFF\xFF";
    $fileKeyHash = md5($fileKeyInput, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 16);
    $encryptedContent = $streamFilter === 'Identity' ? $content : $rc4($content, $contentObjectKey);
    $encryptDictionary = "<< /Filter /Standard /V 4 /R 4 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /V2 /Length 16 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR4AesV2EncryptedPdfWithContent = static function (string $content, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', string $userPassword = '', string $ownerPassword = '', int $permissions = -4, bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };
    $aesV2Encrypt = static function (string $data, string $objectKey, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV2 fixture bytes.');
        }

        return $iv . $encrypted;
    };

    $keyLength = 16;
    $userPasswordPadding = substr($userPassword . $passwordPadding, 0, 32);
    $ownerPasswordPadding = substr($ownerPassword . $passwordPadding, 0, 32);
    $ownerHash = md5($ownerPasswordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($userPasswordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('33445566778899AABBCCDDEEFF001122');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyInput = $userPasswordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId . "\xFF\xFF\xFF\xFF";
    $fileKeyHash = md5($fileKeyInput, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $objectKey = static function (int $objectNumber, int $generation = 0) use ($fileKey): string {
        return substr(md5($fileKey . substr(pack('V', $objectNumber), 0, 3) . substr(pack('V', $generation), 0, 2) . 'sAlT', true), 0, 16);
    };

    $contentObjectKey = $objectKey(5);
    $contentIv = hex2bin('000102030405060708090A0B0C0D0E0F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV2 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV2Encrypt($content, $contentObjectKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 4 /R 4 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV2 /Length 16 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($useEncryptedXrefStream) {
        $encryptStream = static function (string $plain, int $objectNumber, string $iv) use ($streamFilter, $aesV2Encrypt, $objectKey): string {
            return $streamFilter === 'Identity' ? $plain : $aesV2Encrypt($plain, $objectKey($objectNumber), $iv);
        };
        $hexIv = static function (string $hex, string $label): string {
            $iv = hex2bin($hex);
            if ($iv === false) {
                throw new RuntimeException('Invalid AESV2 ' . $label . ' IV fixture.');
            }

            return $iv;
        };

        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored R4 encrypted xref duplicate) Tj ET';
        $encryptedDuplicateContent = $encryptStream($duplicateContent, 5, $hexIv('202122232425262728292A2B2C2D2E2F', 'duplicate content'));
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored R4 encrypted xref object stream stale) Tj ET';
        $encryptedStalePackedPageContent = $encryptStream($stalePackedPageContent, 7, $hexIv('303132333435363738393A3B3C3D3E3F', 'stale packed-page'));

        $pdf = "%PDF-1.6\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);

        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV2 R4 xref/object-stream fixture.');
            }
            $encryptedObjectStream = $encryptStream($compressedObjectStream, 8, $hexIv('404142434445464748494A4B4C4D4E4F', 'object stream'));
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV2 R4 xref stream fixture.');
        }
        $encryptedXrefStream = $encryptStream($compressedXrefStream, 9, $hexIv('505152535455565758595A5B5C5D5E5F', 'xref stream'));
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    return "%PDF-1.6\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR5AesV3EncryptedPdfWithContent = static function (string $content, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', int $permissions = -4, bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false): string {
    $fileKey = hex2bin('00112233445566778899AABBCCDDEEFF102132435465768798A9BACBDCEDFE0F');
    $userValidationSalt = hex2bin('0102030405060708');
    $userKeySalt = hex2bin('1112131415161718');
    $ownerValidationSalt = hex2bin('2122232425262728');
    $ownerKeySalt = hex2bin('3132333435363738');
    $fileId = hex2bin('445566778899AABBCCDDEEFF00112233');
    if ($fileKey === false || $userValidationSalt === false || $userKeySalt === false || $ownerValidationSalt === false || $ownerKeySalt === false || $fileId === false) {
        throw new RuntimeException('Invalid AESV3 encryption fixture bytes.');
    }

    $aes256CbcNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-CBC fixture bytes.');
        }

        return $encrypted;
    };
    $aes256EcbNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-ECB fixture bytes.');
        }

        return $encrypted;
    };
    $aesV3Encrypt = static function (string $data, string $key, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV3 fixture bytes.');
        }

        return $iv . $encrypted;
    };

    $passwordBytes = '';
    $userValue = hash('sha256', $passwordBytes . $userValidationSalt, true) . $userValidationSalt . $userKeySalt;
    $ownerValue = hash('sha256', $passwordBytes . $ownerValidationSalt . $userValue, true) . $ownerValidationSalt . $ownerKeySalt;
    $ueValue = $aes256CbcNoPadding($fileKey, hash('sha256', $passwordBytes . $userKeySalt, true));
    $oeValue = $aes256CbcNoPadding($fileKey, hash('sha256', $passwordBytes . $ownerKeySalt . $userValue, true));
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $permsPlain = pack('V', $permissionsValue) . "\xFF\xFF\xFF\xFF" . 'F' . 'adb' . "\x00\x01\x02\x03";
    $permsValue = $aes256EcbNoPadding($permsPlain, $fileKey);

    $contentIv = hex2bin('404142434445464748494A4B4C4D4E4F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV3 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV3Encrypt($content, $fileKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 5 /R 5 /Length 256 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /OE <" . strtoupper(bin2hex($oeValue)) . "> /UE <" . strtoupper(bin2hex($ueValue)) . "> /P {$permissions} /Perms <" . strtoupper(bin2hex($permsValue)) . "> /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV3 /Length 32 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($useEncryptedXrefStream) {
        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored R5 encrypted xref duplicate) Tj ET';
        $duplicateContentIv = hex2bin('A0A1A2A3A4A5A6A7A8A9AAABACADAEAF');
        if ($duplicateContentIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 duplicate content IV fixture.');
        }
        $encryptedDuplicateContent = $streamFilter === 'Identity' ? $duplicateContent : $aesV3Encrypt($duplicateContent, $fileKey, $duplicateContentIv);
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored R5 encrypted xref object stream stale) Tj ET';
        $stalePackedPageIv = hex2bin('B0B1B2B3B4B5B6B7B8B9BABBBCBDBEBF');
        if ($stalePackedPageIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 stale packed-page IV fixture.');
        }
        $encryptedStalePackedPageContent = $streamFilter === 'Identity' ? $stalePackedPageContent : $aesV3Encrypt($stalePackedPageContent, $fileKey, $stalePackedPageIv);

        $pdf = "%PDF-1.7\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);
        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV3 R5 xref/object-stream fixture.');
            }
            $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
            if ($objectStreamIv === false) {
                throw new RuntimeException('Invalid AESV3 R5 object stream IV fixture.');
            }
            $encryptedObjectStream = $streamFilter === 'Identity' ? $compressedObjectStream : $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV3 R5 xref stream fixture.');
        }
        $xrefStreamIv = hex2bin('909192939495969798999A9B9C9D9E9F');
        if ($xrefStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 xref stream IV fixture.');
        }
        $encryptedXrefStream = $streamFilter === 'Identity' ? $compressedXrefStream : $aesV3Encrypt($compressedXrefStream, $fileKey, $xrefStreamIv);
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    if ($packPageObjectsInObjectStream) {
        $packedObjects = [
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        ];
        $objectStreamHeader = '';
        $objectStreamBody = '';
        foreach ($packedObjects as $objectNumber => $objectBody) {
            $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
            $objectStreamBody .= $objectBody . "\n";
        }
        $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
        if (!is_string($compressedObjectStream)) {
            throw new RuntimeException('Unable to compress AESV3 R5 object stream fixture.');
        }
        $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
        if ($objectStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 object stream IV fixture.');
        }
        $encryptedObjectStream = $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);

        return "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR6AesV3EncryptedPdfWithContent = static function (string $content, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', string $userPassword = '', string $ownerPassword = '', bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false, int $permissions = -4): string {
    $fileKey = hex2bin('31425364758697A8B9CADBECFD0E1F2031425364758697A8B9CADBECFD0E1F20');
    $userValidationSalt = hex2bin('0807060504030201');
    $userKeySalt = hex2bin('1817161514131211');
    $ownerValidationSalt = hex2bin('2827262524232221');
    $ownerKeySalt = hex2bin('3837363534333231');
    $fileId = hex2bin('66554433221100FFEEDDCCBBAA998877');
    if ($fileKey === false || $userValidationSalt === false || $userKeySalt === false || $ownerValidationSalt === false || $ownerKeySalt === false || $fileId === false) {
        throw new RuntimeException('Invalid AESV3 R6 encryption fixture bytes.');
    }

    $aes256CbcNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-CBC fixture bytes.');
        }

        return $encrypted;
    };
    $aes256EcbNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-ECB fixture bytes.');
        }

        return $encrypted;
    };
    $aesV3Encrypt = static function (string $data, string $key, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV3 R6 fixture bytes.');
        }

        return $iv . $encrypted;
    };
    $r6Hash = static function (string $passwordBytes, string $salt, string $userValue = ''): string {
        $key = hash('sha256', $passwordBytes . $salt . $userValue, true);
        for ($round = 0; ; $round++) {
            $input = str_repeat($passwordBytes . $key . $userValue, 64);
            $encrypted = openssl_encrypt($input, 'aes-128-cbc', substr($key, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($key, 16, 16));
            if (!is_string($encrypted) || $encrypted === '') {
                throw new RuntimeException('Unable to hash AESV3 R6 fixture bytes.');
            }

            $hashSelector = 0;
            for ($index = 0; $index < 16; $index++) {
                $hashSelector = (($hashSelector * 256) + ord($encrypted[$index])) % 3;
            }
            $key = hash(match ($hashSelector) {
                0 => 'sha256',
                1 => 'sha384',
                default => 'sha512',
            }, $encrypted, true);

            if ($round >= 63 && ord($encrypted[strlen($encrypted) - 1]) <= $round - 32) {
                break;
            }
        }

        return substr($key, 0, 32);
    };

    $passwordBytes = substr($userPassword, 0, 127);
    $ownerPasswordBytes = substr($ownerPassword, 0, 127);
    $userValue = $r6Hash($passwordBytes, $userValidationSalt) . $userValidationSalt . $userKeySalt;
    $ownerValue = $r6Hash($ownerPasswordBytes, $ownerValidationSalt, $userValue) . $ownerValidationSalt . $ownerKeySalt;
    $ueValue = $aes256CbcNoPadding($fileKey, $r6Hash($passwordBytes, $userKeySalt));
    $oeValue = $aes256CbcNoPadding($fileKey, $r6Hash($ownerPasswordBytes, $ownerKeySalt, $userValue));
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $permsPlain = pack('V', $permissionsValue) . "\xFF\xFF\xFF\xFF" . 'F' . 'adb' . "\x04\x05\x06\x07";
    $permsValue = $aes256EcbNoPadding($permsPlain, $fileKey);

    $contentIv = hex2bin('606162636465666768696A6B6C6D6E6F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV3 R6 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV3Encrypt($content, $fileKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 5 /R 6 /Length 256 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /OE <" . strtoupper(bin2hex($oeValue)) . "> /UE <" . strtoupper(bin2hex($ueValue)) . "> /P {$permissions} /Perms <" . strtoupper(bin2hex($permsValue)) . "> /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV3 /Length 32 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($useEncryptedXrefStream) {
        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored encrypted xref duplicate) Tj ET';
        $duplicateContentIv = hex2bin('A0A1A2A3A4A5A6A7A8A9AAABACADAEAF');
        if ($duplicateContentIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 duplicate content IV fixture.');
        }
        $encryptedDuplicateContent = $streamFilter === 'Identity' ? $duplicateContent : $aesV3Encrypt($duplicateContent, $fileKey, $duplicateContentIv);
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored encrypted xref object stream stale) Tj ET';
        $stalePackedPageIv = hex2bin('B0B1B2B3B4B5B6B7B8B9BABBBCBDBEBF');
        if ($stalePackedPageIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 stale packed-page IV fixture.');
        }
        $encryptedStalePackedPageContent = $streamFilter === 'Identity' ? $stalePackedPageContent : $aesV3Encrypt($stalePackedPageContent, $fileKey, $stalePackedPageIv);

        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);
        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV3 R6 xref/object-stream fixture.');
            }
            $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
            if ($objectStreamIv === false) {
                throw new RuntimeException('Invalid AESV3 R6 object stream IV fixture.');
            }
            $encryptedObjectStream = $streamFilter === 'Identity' ? $compressedObjectStream : $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV3 R6 xref stream fixture.');
        }
        $xrefStreamIv = hex2bin('909192939495969798999A9B9C9D9E9F');
        if ($xrefStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 xref stream IV fixture.');
        }
        $encryptedXrefStream = $streamFilter === 'Identity' ? $compressedXrefStream : $aesV3Encrypt($compressedXrefStream, $fileKey, $xrefStreamIv);
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    if ($packPageObjectsInObjectStream) {
        $packedObjects = [
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        ];
        $objectStreamHeader = '';
        $objectStreamBody = '';
        foreach ($packedObjects as $objectNumber => $objectBody) {
            $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
            $objectStreamBody .= $objectBody . "\n";
        }
        $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
        if (!is_string($compressedObjectStream)) {
            throw new RuntimeException('Unable to compress AESV3 R6 object stream fixture.');
        }
        $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
        if ($objectStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 object stream IV fixture.');
        }
        $encryptedObjectStream = $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);

        return "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$trueTypeWithFormat4CMap = static function (array $codes): string {
    $codes = array_values(array_unique(array_filter($codes, static fn (int $code): bool => $code >= 0 && $code <= 255)));
    sort($codes, SORT_NUMERIC);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $code) {
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', 0);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    return pack('Nnnnn', 0x00010000, 1, 16, 0, 0)
        . 'cmap'
        . pack('NNN', 0, 28, strlen($cmap))
        . $cmap;
};

$trueTypeWithFormat4CMapAndPostNames = static function (array $codeToGlyphName): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);
    $glyphNames = array_values($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $index => $code) {
        $glyphId = $index + 1;
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    $post = pack('NNnnNNNNNn', 0x00020000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . pack('n', 0);
    foreach ($glyphNames as $index => $glyphName) {
        $post .= pack('n', 258 + $index);
    }
    foreach ($glyphNames as $glyphName) {
        $post .= chr(strlen($glyphName)) . $glyphName;
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat4CMapAndPost25Names = static function (array $codeToGlyphName): string {
    $standardPostIndexByName = [
        'space' => 3,
        'D' => 39,
        'F' => 41,
        'K' => 46,
        'O' => 50,
        'P' => 51,
    ];
    $codeToGlyphName = array_filter(
        $codeToGlyphName,
        static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && isset($standardPostIndexByName[$glyphName]),
        ARRAY_FILTER_USE_BOTH
    );
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);
    $glyphNames = array_values($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $index => $code) {
        $glyphId = $index + 1;
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    $post = pack('NNnnNNNNNn', 0x00025000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . chr(0);
    foreach ($glyphNames as $index => $glyphName) {
        $glyphId = $index + 1;
        $post .= chr(($standardPostIndexByName[$glyphName] - $glyphId) & 0xFF);
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat4CMapAndPost10Names = static function (array $codeToGlyphName): string {
    $standardPostIndexByName = [
        'space' => 3,
        'D' => 39,
        'F' => 41,
        'K' => 46,
        'O' => 50,
        'P' => 51,
    ];
    $codeToGlyphName = array_filter(
        $codeToGlyphName,
        static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && isset($standardPostIndexByName[$glyphName]),
        ARRAY_FILTER_USE_BOTH
    );
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $code) {
        $glyphId = $standardPostIndexByName[$codeToGlyphName[$code]];
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;
    $post = pack('NNnnNNNNN', 0x00010000, 0, 0, 0, 0, 0, 0, 0, 0);

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithCMapSubtableAndPostNames = static function (string $subtable, int $platformId, int $encodingId, array $glyphNames): string {
    $cmap = pack('nnnnN', 0, 1, $platformId, $encodingId, 12) . $subtable;
    $post = pack('NNnnNNNNNn', 0x00020000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . pack('n', 0);
    foreach ($glyphNames as $index => $glyphName) {
        $post .= pack('n', 258 + $index);
    }
    foreach ($glyphNames as $glyphName) {
        $post .= chr(strlen($glyphName)) . $glyphName;
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat0CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);

    $glyphIds = array_fill(0, 256, 0);
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIds[$code] = count($glyphNames);
    }

    $glyphIdArray = '';
    foreach ($glyphIds as $glyphId) {
        $glyphIdArray .= chr($glyphId & 0xFF);
    }

    $format0 = pack('nnn', 0, 262, 0) . $glyphIdArray;
    return $trueTypeWithCMapSubtableAndPostNames($format0, 0, 3, $glyphNames);
};

$trueTypeWithFormat6CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnnnn', 6, 10, 0, 0, 0), 3, 1, []);
    }

    $firstCode = min(array_keys($codeToGlyphName));
    $lastCode = max(array_keys($codeToGlyphName));
    $glyphIdByCode = [];
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIdByCode[$code] = count($glyphNames);
    }

    $glyphIds = '';
    for ($code = $firstCode; $code <= $lastCode; $code++) {
        $glyphIds .= pack('n', $glyphIdByCode[$code] ?? 0);
    }

    $entryCount = $lastCode - $firstCode + 1;
    $format6 = pack('nnnnn', 6, 10 + (2 * $entryCount), 0, $firstCode, $entryCount) . $glyphIds;
    return $trueTypeWithCMapSubtableAndPostNames($format6, 3, 1, $glyphNames);
};

$trueTypeWithFormat8CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNN', 8, 0, 8208, 0) . str_repeat("\0", 8192) . pack('N', 0), 3, 10, []);
    }

    $glyphNames = [];
    $groups = '';
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $groups .= pack('NNN', $code, $code, count($glyphNames));
    }

    $groupCount = count($codeToGlyphName);
    $format8 = pack('nnNN', 8, 0, 8208 + (12 * $groupCount), 0)
        . str_repeat("\0", 8192)
        . pack('N', $groupCount)
        . $groups;
    return $trueTypeWithCMapSubtableAndPostNames($format8, 3, 10, $glyphNames);
};

$trueTypeWithFormat10CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNNNN', 10, 0, 20, 0, 0, 0), 3, 10, []);
    }

    $firstCode = min(array_keys($codeToGlyphName));
    $lastCode = max(array_keys($codeToGlyphName));
    $glyphIdByCode = [];
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIdByCode[$code] = count($glyphNames);
    }

    $glyphIds = '';
    for ($code = $firstCode; $code <= $lastCode; $code++) {
        $glyphIds .= pack('n', $glyphIdByCode[$code] ?? 0);
    }

    $entryCount = $lastCode - $firstCode + 1;
    $format10 = pack('nnNNNN', 10, 0, 20 + (2 * $entryCount), 0, $firstCode, $entryCount) . $glyphIds;
    return $trueTypeWithCMapSubtableAndPostNames($format10, 3, 10, $glyphNames);
};

$trueTypeWithFormat13CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNNN', 13, 0, 16, 0, 0), 3, 10, []);
    }

    $glyphNames = [];
    $groups = '';
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $groups .= pack('NNN', $code, $code, count($glyphNames));
    }

    $groupCount = count($codeToGlyphName);
    $format13 = pack('nnNNN', 13, 0, 16 + (12 * $groupCount), 0, $groupCount) . $groups;
    return $trueTypeWithCMapSubtableAndPostNames($format13, 3, 10, $glyphNames);
};

$cffType1CWithFormat0Encoding = static function (array $codeToGlyphName): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 0 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    $glyphNames = array_values($codeToGlyphName);
    $codes = array_keys($codeToGlyphName);
    $strings = array_values(array_unique($glyphNames));
    $stringSidByName = [];
    foreach ($strings as $index => $glyphName) {
        $stringSidByName[$glyphName] = 391 + $index;
    }

    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['TinyCFF']);
    $stringIndex = $buildIndex($strings);
    $globalSubrIndex = $buildIndex([]);
    $charset = "\0";
    foreach ($glyphNames as $glyphName) {
        $charset .= pack('n', $stringSidByName[$glyphName]);
    }
    $encoding = "\0" . chr(count($codes));
    foreach ($codes as $code) {
        $encoding .= chr($code);
    }
    $charStrings = $buildIndex(array_fill(0, count($glyphNames) + 1, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $afterTopOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $charsetOffset = $afterTopOffset;
        $encodingOffset = $charsetOffset + strlen($charset);
        $charStringsOffset = $encodingOffset + strlen($encoding);
        $nextTopDict = $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($encodingOffset) . chr(16)
            . $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charset
        . $encoding
        . $charStrings;
};

$cffType1CWithDefaultStandardEncoding = static function (int $glyphCount): string {
    $glyphCount = max(1, $glyphCount);
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['DefaultCFF']);
    $stringIndex = $buildIndex([]);
    $globalSubrIndex = $buildIndex([]);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $charStringsOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $nextTopDict = $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charStrings;
};

$cffType1CWithPredefinedCharsetAndEncoding = static function (int $charsetOffset, int $encodingOffset, int $glyphCount): string {
    $glyphCount = max(1, $glyphCount);
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['PredefinedCFF']);
    $stringIndex = $buildIndex([]);
    $globalSubrIndex = $buildIndex([]);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $charStringsOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $nextTopDict = $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($encodingOffset) . chr(16)
            . $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charStrings;
};

$cffCidKeyedType0CWithFormat0Charset = static function (array $cids, string $ordering = 'UCS'): string {
    $cids = array_values(array_filter($cids, static fn (int $cid): bool => $cid >= 0 && $cid <= 0xFFFF));
    $glyphCount = count($cids) + 1;
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['CidCFF']);
    $stringIndex = $buildIndex(['Adobe', $ordering]);
    $registrySid = 391;
    $orderingSid = 392;
    $globalSubrIndex = $buildIndex([]);
    $charset = "\0";
    foreach ($cids as $cid) {
        $charset .= pack('n', $cid);
    }
    $fdSelect = "\0" . str_repeat("\0", $glyphCount);
    $fdArray = $buildIndex(['']);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $afterTopOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $charsetOffset = $afterTopOffset;
        $fdSelectOffset = $charsetOffset + strlen($charset);
        $fdArrayOffset = $fdSelectOffset + strlen($fdSelect);
        $charStringsOffset = $fdArrayOffset + strlen($fdArray);
        $nextTopDict = $encodeCffInteger($registrySid) . $encodeCffInteger($orderingSid) . $encodeCffInteger(0) . chr(12) . chr(30)
            . $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($charStringsOffset) . chr(17)
            . $encodeCffInteger($fdArrayOffset) . chr(12) . chr(36)
            . $encodeCffInteger($fdSelectOffset) . chr(12) . chr(37);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charset
        . $fdSelect
        . $fdArray
        . $charStrings;
};

$pdfWithCompressedObjectStream = static function (): string {
    $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
    $cmap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<0001> <004F0062006A006500630074002000530074007200650061006D>\n"
        . "<0002> <00200054006500780074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /ObjectStreamCMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $packedObjects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type0 /BaseFont /CompressedSubset /Encoding /Identity-H /ToUnicode 6 0 R >>',
    ];
    $headerParts = [];
    $body = '';
    foreach ($packedObjects as $objectNumber => $objectBody) {
        $headerParts[] = $objectNumber . ' ' . strlen($body);
        $body .= $objectBody . "\n";
    }
    $header = implode(' ', $headerParts) . "\n";
    $objectStream = gzcompress($header . $body);

    return "%PDF-1.5\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream\nendobj\n%%EOF";
};

$pdfWithIncrementalObjectStreamUpdate = static function (): string {
    $oldContent = 'BT /F1 12 Tf 72 720 Td (Old Revision Text) Tj ET';
    $newContent = 'BT /F1 12 Tf 72 720 Td (Incremental Object Stream Text) Tj ET';
    $updatedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>';
    $header = '3 0' . "\n";
    $objectStream = gzcompress($header . $updatedPage . "\n");

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($oldContent) . " >>\nstream\n{$oldContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($newContent) . " >>\nstream\n{$newContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /ObjStm /N 1 /First " . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream\nendobj\n%%EOF";
};

$pdfWithCatalogReachableAndOrphanPages = static function (): string {
    $activeContent = 'BT /F1 12 Tf 72 720 Td (Catalog Reachable Page) Tj ET';
    $orphanContent = 'BT /F1 12 Tf 72 720 Td (Stale Orphan Page) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 7 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($activeContent) . " >>\nstream\n{$activeContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($orphanContent) . " >>\nstream\n{$orphanContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithGeneratedContentReference = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Generation One Content) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Generation Zero Content) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 1 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 1 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithSimpleFontWidthsForPositioning = static function (): string {
    $widths = array_fill(0, 91, 500);
    foreach ([87, 100, 101, 105] as $code) {
        $widths[$code - 32] = 1000;
    }
    $widthArray = implode(' ', $widths);
    $content = 'BT /Fwide 12 Tf 1 0 0 1 72 720 Tm (Wide) Tj 1 0 0 1 108 720 Tm (Tail) Tj ET '
        . 'BT /Fwide 12 Tf 1 0 0 1 72 704 Tm [(Wide)] TJ 1 0 0 1 108 704 Tm (Tail) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fwide 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 122 /Widths [{$widthArray}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithCidWidthsForPositioning = static function (): string {
    $cmap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
        . "/CMapName /CidWidthsMap def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<0001> <0057>\n"
        . "<0002> <0069>\n"
        . "<0003> <0064>\n"
        . "<0004> <0065>\n"
        . "<0005> <0054>\n"
        . "<0006> <0061>\n"
        . "<0007> <0069>\n"
        . "<0008> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CidWidthsMap defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 108 720 Tm <0005000600070008> Tj ET '
        . 'BT /FcidIndirect 12 Tf 1 0 0 1 72 704 Tm [<0001000200030004>] TJ 1 0 0 1 108 704 Tm <0005000600070008> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /FcidIndirect 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CidWidths /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CidWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CidWidthsIndirect /Encoding /Identity-H /DescendantFonts [9 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CidWidthsIndirect /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W 10 0 R >>\nendobj\n"
        . "10 0 obj\n{$cidWidths}\nendobj\n%%EOF";
};

$pdfWithNonIdentityCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
        . "/CMapName /NonIdentityWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<0100> <01FF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<0101> <0057>\n"
        . "<0102> <0069>\n"
        . "<0103> <0064>\n"
        . "<0104> <0065>\n"
        . "<0105> <0054>\n"
        . "<0106> <0061>\n"
        . "<0107> <0069>\n"
        . "<0108> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NonIdentityWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NonIdentityRangeWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<0100> <01FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0101> <0108> 1\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /NonIdentityRangeWidths defineresource pop\n"
        . "end\n"
        . "end";
    $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NonIdentityCharWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<0100> <01FF>\n"
        . "endcodespacerange\n"
        . "8 begincidchar\n"
        . "<0101> 1\n"
        . "<0102> 2\n"
        . "<0103> 3\n"
        . "<0104> 4\n"
        . "<0105> 5\n"
        . "<0106> 6\n"
        . "<0107> 7\n"
        . "<0108> 8\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NonIdentityCharWidths defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
    $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <0101010201030104> Tj 1 0 0 1 108 720 Tm <0105010601070108> Tj ET '
        . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<0101010201030104>] TJ 1 0 0 1 108 704 Tm <0105010601070108> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NonIdentityRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NonIdentityWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NonIdentityCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
};

$pdfWithCidNotdefWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
        . "/CMapName /NotdefWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<01> <08>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<01> <0045>\n"
        . "<02> <0064>\n"
        . "<03> <0067>\n"
        . "<04> <0065>\n"
        . "<05> <0043>\n"
        . "<06> <0061>\n"
        . "<07> <0073>\n"
        . "<08> <0065>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NotdefWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NotdefRangeWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<01> <08>\n"
        . "endcodespacerange\n"
        . "1 beginnotdefrange\n"
        . "<01> <04> 30\n"
        . "endnotdefrange\n"
        . "endcmap\n"
        . "CMapName currentdict /NotdefRangeWidths defineresource pop\n"
        . "end\n"
        . "end";
    $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NotdefCharWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<01> <08>\n"
        . "endcodespacerange\n"
        . "4 beginnotdefchar\n"
        . "<01> 30\n"
        . "<02> 30\n"
        . "<03> 30\n"
        . "<04> 30\n"
        . "endnotdefchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NotdefCharWidths defineresource pop\n"
        . "end\n"
        . "end";
    $content = 'BT /FnotdefRange 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj 1 0 0 1 126 720 Tm <05060708> Tj ET '
        . 'BT /FnotdefChar 12 Tf 1 0 0 1 72 704 Tm [<01020304>] TJ 1 0 0 1 126 704 Tm <05060708> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /FnotdefRange 4 0 R /FnotdefChar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotdefWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W [30 30 1000] >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
};

$pdfWithNamedCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
        . "/CMapName /NamedWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<0200> <03FF>\n"
        . "endcodespacerange\n"
        . "16 beginbfchar\n"
        . "<0201> <0057>\n"
        . "<0202> <0069>\n"
        . "<0203> <0064>\n"
        . "<0204> <0065>\n"
        . "<0205> <0054>\n"
        . "<0206> <0061>\n"
        . "<0207> <0069>\n"
        . "<0208> <006C>\n"
        . "<0301> <0057>\n"
        . "<0302> <0069>\n"
        . "<0303> <0064>\n"
        . "<0304> <0065>\n"
        . "<0305> <0054>\n"
        . "<0306> <0061>\n"
        . "<0307> <0069>\n"
        . "<0308> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NamedWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NamedRangeWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<0200> <02FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0201> <0208> 1\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /NamedRangeWidths defineresource pop\n"
        . "end\n"
        . "end";
    $baseEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NamedBaseWidths def\n"
        . "/CMapType 1 def\n"
        . "1 begincodespacerange\n"
        . "<0300> <03FF>\n"
        . "endcodespacerange\n"
        . "8 begincidchar\n"
        . "<0301> 1\n"
        . "<0302> 2\n"
        . "<0303> 3\n"
        . "<0304> 4\n"
        . "<0305> 5\n"
        . "<0306> 6\n"
        . "<0307> 7\n"
        . "<0308> 8\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /NamedBaseWidths defineresource pop\n"
        . "end\n"
        . "end";
    $derivedEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
        . "/CMapName /NamedDerivedWidths def\n"
        . "/CMapType 1 def\n"
        . "/NamedBaseWidths usecmap\n"
        . "endcmap\n"
        . "CMapName currentdict /NamedDerivedWidths defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
    $content = 'BT /Fnamed 12 Tf 1 0 0 1 72 720 Tm <0201020202030204> Tj 1 0 0 1 108 720 Tm <0205020602070208> Tj ET '
        . 'BT /Fderived 12 Tf 1 0 0 1 72 704 Tm [<0301030203030304>] TJ 1 0 0 1 108 704 Tm <0305030603070308> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fnamed 4 0 R /Fderived 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NamedRangeWidths /Encoding /NamedRangeWidths /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NamedWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NamedDerivedWidths /Encoding /NamedDerivedWidths /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($baseEncodingCMap) . " >>\nstream\n{$baseEncodingCMap}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Length " . strlen($derivedEncodingCMap) . " >>\nstream\n{$derivedEncodingCMap}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 0 >> def\n"
        . "/CMapName /PredefinedGbWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<00> <80>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<21> <0057>\n"
        . "<22> <0069>\n"
        . "<23> <0064>\n"
        . "<24> <0065>\n"
        . "<25> <0054>\n"
        . "<26> <0061>\n"
        . "<27> <0069>\n"
        . "<28> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PredefinedGbWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[814 [1000 1000 1000 1000] 818 821 500]';
    $content = 'BT /Fgb 12 Tf 1 0 0 1 72 720 Tm <21222324> Tj 1 0 0 1 108 720 Tm <25262728> Tj ET '
        . 'BT /Fgb 12 Tf 1 0 0 1 72 704 Tm [<21222324>] TJ 1 0 0 1 108 704 Tm <25262728> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fgb 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedGbWidths /Encoding /GB-EUC-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedGbWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedCnsCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> def\n"
        . "/CMapName /PredefinedCnsWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<A140> <A1FE>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<A140> <0057>\n"
        . "<A141> <0069>\n"
        . "<A142> <0064>\n"
        . "<A143> <0065>\n"
        . "<A144> <0054>\n"
        . "<A145> <0061>\n"
        . "<A146> <0069>\n"
        . "<A147> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PredefinedCnsWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[99 [1000 1000 1000 1000] 103 106 500]';
    $content = 'BT /Fcns 12 Tf 1 0 0 1 72 720 Tm <a140a141a142a143> Tj 1 0 0 1 112 720 Tm <a144a145a146a147> Tj ET '
        . 'BT /Fcns 12 Tf 1 0 0 1 72 704 Tm [<a140a141a142a143>] TJ 1 0 0 1 112 704 Tm <a144a145a146a147> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcns 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedCnsWidths /Encoding /B5pc-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedCnsWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedInheritedCnsCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> def\n"
        . "/CMapName /PredefinedInheritedCnsWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<A140> <A1FE>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<A140> <0057>\n"
        . "<A141> <0069>\n"
        . "<A142> <0064>\n"
        . "<A143> <0065>\n"
        . "<A144> <0054>\n"
        . "<A145> <0061>\n"
        . "<A146> <0069>\n"
        . "<A147> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PredefinedInheritedCnsWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[99 [1000 1000 1000 1000] 103 106 500]';
    $content = 'BT /Feten 12 Tf 1 0 0 1 72 720 Tm <a140a141a142a143> Tj 1 0 0 1 112 720 Tm <a144a145a146a147> Tj ET '
        . 'BT /Feten 12 Tf 1 0 0 1 72 704 Tm [<a140a141a142a143>] TJ 1 0 0 1 112 704 Tm <a144a145a146a147> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Feten 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedInheritedCnsWidths /Encoding /ETenms-B5-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedInheritedCnsWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedJapanCidWidthsForPositioning = static function (): string {
    $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 0 >> def\n"
        . "/CMapName /PredefinedJapanWidthsToUnicode def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<00> <80>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<20> <0057>\n"
        . "<21> <0069>\n"
        . "<22> <0064>\n"
        . "<23> <0065>\n"
        . "<24> <0054>\n"
        . "<25> <0061>\n"
        . "<26> <0069>\n"
        . "<27> <006C>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PredefinedJapanWidthsToUnicode defineresource pop\n"
        . "end\n"
        . "end";
    $cidWidths = '[231 [1000 1000 1000 1000] 235 238 500]';
    $content = 'BT /Frksj 12 Tf 1 0 0 1 72 720 Tm <20212223> Tj 1 0 0 1 108 720 Tm <24252627> Tj ET '
        . 'BT /Frksj 12 Tf 1 0 0 1 72 704 Tm [<20212223>] TJ 1 0 0 1 108 704 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frksj 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedJapanWidths /Encoding /RKSJ-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedJapanWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedUnicodeSourceCidMapWithoutToUnicode = static function (): string {
    $cidWidths = '[56 [1000] 69 [1000 1000] 74 [1000]]';
    $content = 'BT /Funi 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj 1 0 0 1 108 720 Tm <005400610069006c> Tj ET '
        . 'BT /Funi 12 Tf 1 0 0 1 72 704 Tm [<0057006900640065>] TJ 1 0 0 1 108 704 Tm <005400610069006c> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Funi 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeSource /Encoding /UniJIS-UCS2-H /DescendantFonts [5 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeSource /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 4 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithPredefinedUtf8SourceFallbackWithoutToUnicode = static function (): string {
    $content = 'BT /Futf8 12 Tf 72 720 Td <f09f988057696465> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Futf8 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeUtf8Source /Encoding /UniJIS-UTF8-H /DescendantFonts [5 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeUtf8Source /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithDerivedPredefinedUtf8SourceFallbackWithoutToUnicode = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> def\n"
        . "/CMapName /CustomDerivedSource def\n"
        . "/CMapType 1 def\n"
        . "/UniJIS-UTF8-H usecmap\n"
        . "endcmap\n"
        . "CMapName currentdict /CustomDerivedSource defineresource pop\n"
        . "end\n"
        . "end";
    $content = 'BT /Fderived 12 Tf 72 720 Td <f09f988057696465> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fderived 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DerivedUnicodeUtf8Source /Encoding 5 0 R /DescendantFonts [6 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DerivedUnicodeUtf8Source /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithVerticalPredefinedUtf8SourceWithoutToUnicode = static function (): string {
    $content = 'BT /Futf8v 12 Tf 1 0 0 1 100 700 Tm <566572746963616c> Tj 1 0 0 1 100 604 Tm <2054657874> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Futf8v 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeUtf8Vertical /Encoding /UniJIS-UTF8-V /DescendantFonts [5 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeUtf8Vertical /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithRotatedTextMatrixForPositioning = static function () use ($pdfWithContent): string {
    return $pdfWithContent(
        'BT /F1 12 Tf 0 1 -1 0 100 100 Tm (Data) Tj 0 1 -1 0 100 126 Tm (base) Tj '
        . '0 1 -1 0 100 170 Tm (Tool) Tj 0 1 -1 0 84 100 Tm (Next) Tj 0 1 -1 0 84 126 Tm (Line) Tj ET'
    );
};

$pdfWithScaledCtmTextMatrixForPositioning = static function () use ($pdfWithContent): string {
    return $pdfWithContent(
        'q 2 0 0 1 0 0 cm BT /F1 12 Tf 1 0 0 1 72 720 Tm (Data) Tj '
        . '1 0 0 1 104 720 Tm (Tool) Tj ET Q '
        . 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (Plain) Tj 1 0 0 1 104 700 Tm (Text) Tj ET'
    );
};

$pdfWithPartialPageExtractionIssues = static function (): string {
    $readableContent = 'BT /F1 12 Tf 72 720 Td (Readable Page Text) Tj ET';
    $unsupportedContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Page Text) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R 8 0 R] /Count 3 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($readableContent) . " >>\nstream\n{$readableContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /DCTDecode /Length " . strlen($unsupportedContent) . " >>\nstream\n{$unsupportedContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 99 0 R >>\nendobj\n%%EOF";
};

$pdfWithFormXObjectPageExtractionIssues = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Page Text) Tj ET q /FmBad Do Q q /FmBroken Do Q';
    $unsupportedFormContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Form Text) Tj ET';
    $brokenFlateFormContent = 'not-a-flate-stream';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> /XObject << /FmBad 6 0 R /FmBroken 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Filter /DCTDecode /Length " . strlen($unsupportedFormContent) . " >>\nstream\n{$unsupportedFormContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Filter /FlateDecode /Length " . strlen($brokenFlateFormContent) . " >>\nstream\n{$brokenFlateFormContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithUnresolvedXObjectPageExtractionIssues = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible XObject Resource Text) Tj ET q /FmMissing Do Q q /FmAbsent Do Q q /FmMalformed Do Q';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> /XObject << /FmMissing 99 0 R /FmMalformed 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] >>\nendobj\n%%EOF";
};

$pdfWithXrefFreedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Current Content) Tj ET';
    $freedContent = 'BT /F1 12 Tf 72 720 Td (Freed Stale Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents [5 0 R 8 0 R] >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        8 => "<< /Length " . strlen($freedContent) . " >>\nstream\n{$freedContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n";
    for ($objectNumber = 0; $objectNumber <= 8; $objectNumber++) {
        if ($objectNumber === 0 || $objectNumber === 8 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    return $pdf . "trailer << /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithXrefSelectedDuplicateContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Later Duplicate Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    return $pdf . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithEarlierValidStartxrefAfterMalformedAppend = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Earlier Valid Xref Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Bad Append Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    return $pdf . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n999999\n%%EOF";
};

$xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
    return chr($type) . pack('N', $field2) . pack('n', $field3);
};

$xrefStreamPngPredictorRows = static function (string $stream, int $columns): string {
    $encoded = '';
    foreach (str_split($stream, $columns) as $row) {
        $encoded .= "\0" . $row;
    }

    return $encoded;
};

$pdfWithXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Xref Stream Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithPredictorXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry, $xrefStreamPngPredictorRows): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Predictor Xref Stream Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Predictor Xref Stream Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $encodedRows = $xrefStreamPngPredictorRows(implode('', $entries), 7);
    $xrefStream = gzcompress($encodedRows);

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 7 >> /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Zero Width Xref Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Zero Width Xref Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = '';
    foreach ([1, 2, 3, 4, 5] as $objectNumber) {
        $entries .= pack('N', $offsets[$objectNumber]);
    }
    $entries .= pack('N', $xrefOffset);
    $xrefStream = gzcompress($entries);

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [0 4 0] /Index [1 5 9 1] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect Xref Arrays Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect Xref Arrays Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        10 => '[1 4 2]',
        11 => '[0 12]',
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 12 /Root 1 0 R /W 10 0 R /Index 11 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Latest Indirect Xref Arrays Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Latest Indirect Xref Arrays Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        10 => '[1 4 2]',
        11 => '[0 12]',
    ];

    $pdf = "%PDF-1.5\n";
    $pdf .= "10 0 obj\n[1 1 1]\nendobj\n";
    $pdf .= "11 0 obj\n[0 6]\nendobj\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 12 /Root 1 0 R /W 10 0 R /Index 11 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Latest Indirect Xref Size Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Latest Indirect Xref Size Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 8 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        8 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        12 => '13',
    ];

    $pdf = "%PDF-1.5\n";
    $pdf .= "12 0 obj\n6\nendobj\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "8 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 12; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Root 1 0 R /W [1 4 2] /Size 12 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectPrevXrefChainSelectedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect Prev Chain Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect Prev Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$baseXrefOffset}\n%%EOF\n";

    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";
    $prevOffsetObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n{$baseXrefOffset}\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $prevOffsetObjectOffset);

    return $pdf . "trailer << /Size 21 /Root 1 0 R /Prev 20 0 R >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

$pdfWithNestedTrailerPrevXrefChainSelectedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Nested Trailer Prev Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Nested Trailer Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$baseXrefOffset}\n%%EOF\n";

    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";
    $latestObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n<< /Note (latest xref only) >>\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $latestObjectOffset);

    return $pdf . "trailer << /Size 21 /Root 1 0 R /Info << /Producer (Nested Trailer) >> /Prev {$baseXrefOffset} >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

$pdfWithIndirectXRefStmHybridSelectedContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect XRefStm Selected Content) Tj ET';
    $tableDuplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect XRefStm Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $tableDuplicateOffset = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Length " . strlen($tableDuplicateContent) . " >>\nstream\n{$tableDuplicateContent}\nendstream\nendobj\n";

    $xrefStreamOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefStreamOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefStreamOffsetObjectOffset = strlen($pdf);
    $pdf .= "10 0 obj\n{$xrefStreamOffset}\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 11\n";
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 9 && $objectNumber !== 10)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 5
            ? $tableDuplicateOffset
            : ($objectNumber === 9 ? $xrefStreamOffset : ($objectNumber === 10 ? $xrefStreamOffsetObjectOffset : $offsets[$objectNumber]));
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 11 /Root 1 0 R /XRefStm 10 0 R >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithXrefStreamCompressedPageSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Stale Packed Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [0 11] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithPredictorObjectStreamSelection = static function () use ($xrefStreamEntry, $xrefStreamPngPredictorRows): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Predictor ObjStm Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Predictor ObjStm Stale Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $encodedObjectStreamRows = $xrefStreamPngPredictorRows($objectStream, strlen($objectStream));
    $encodedObjectStream = gzcompress($encodedObjectStreamRows);
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns ' . strlen($objectStream) . ' >> /Length ' . strlen($encodedObjectStream) . " >>\nstream\n{$encodedObjectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [0 11] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectObjectStreamHeaderSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect ObjStm Header Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Indirect ObjStm Header Stale Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 11 0 R /First 12 0 R /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
        11 => '2',
        12 => (string) strlen($objectStreamHeader),
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 12; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 13 /Root 1 0 R /W [1 4 2] /Index [0 13] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Selected Content) Tj ET';
    $tableDuplicateContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Table Duplicate Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $tableDuplicateOffset = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Length " . strlen($tableDuplicateContent) . " >>\nstream\n{$tableDuplicateContent}\nendstream\nendobj\n";

    $xrefStreamOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefStreamOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 10\n";
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 9)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 5 ? $tableDuplicateOffset : ($objectNumber === 9 ? $xrefStreamOffset : $offsets[$objectNumber]);
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 10 /Root 1 0 R /XRefStm {$xrefStreamOffset} >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamCompressedPageSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Stale Packed Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefStreamOffset = strlen($pdf);
    $xrefStream = gzcompress(
        $xrefStreamEntry(2, 7, 0)
        . $xrefStreamEntry(1, $xrefStreamOffset, 0)
    );
    $pdf .= "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [3 1 10 1] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 11\n";
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        if ($objectNumber === 0 || $objectNumber === 3 || $objectNumber === 9 || (!isset($offsets[$objectNumber]) && $objectNumber !== 10)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 10 ? $xrefStreamOffset : $offsets[$objectNumber];
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 11 /Root 1 0 R /XRefStm {$xrefStreamOffset} >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamAndTablePreviousBranches = static function () use ($xrefStreamEntry): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Hybrid Prev Branch Text) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $pageBranchXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 4\n";
    $pdf .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= 3; $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 10 >>\n";

    $objects = [
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
    ];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $contentBranchXrefOffset = strlen($pdf);
    $pdf .= "xref\n4 2\n";
    for ($objectNumber = 4; $objectNumber <= 5; $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 10 >>\n";

    $xrefStreamOffset = strlen($pdf);
    $xrefStream = gzcompress($xrefStreamEntry(1, $xrefStreamOffset, 0));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [9 1] /Prev {$pageBranchXrefOffset} /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n9 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $xrefStreamOffset);

    return $pdf . "trailer << /Size 10 /Root 1 0 R /XRefStm {$xrefStreamOffset} /Prev {$contentBranchXrefOffset} >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

return [
    'reads searchable pdf text into shared ast blocks' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 24 Tf 72 720 Td (PDF Demo Title) Tj T* '
            . '(Visit https://example.test/path.) Tj T* '
            . '(- First item) Tj T* '
            . '(- Second item) Tj ET'
        );

        $document = (new PdfReader())->read($pdf);
        $meta = $document->attr('meta');

        $t->same('document', $document->type);
        $t->same(PdfTextExtractor::class, $meta['pdfExtractor']);
        $t->same('heading', $document->children[0]->type);
        $t->same('PDF Demo Title', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('link', $document->children[1]->children[1]->type);
        $t->same('https://example.test/path', $document->children[1]->children[1]->attr('url'));
        $t->same('bullet_list', $document->children[2]->type);
        $t->same(4, $meta['pdfTextLines']);
    },
    'uses pdf link annotations for visible non url text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Intro sentence.) Tj 0 -16 Td (Read docs here.) Tj 0 -16 Td (Plain coda.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 163 716] /A << /S /URI /URI (https://example.test/docs) >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[1]->type);
        $t->same('link', $document->children[1]->children[0]->type);
        $t->same('https://example.test/docs', $document->children[1]->children[0]->attr('url'));
        $t->same('Read docs here.', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same(1, count($meta['pdfLinkAnnotations']));
        $t->same(1, count($meta['pdfAppliedLinkAnnotations']));
        $t->same('Read docs here.', $meta['pdfLinkAnnotations'][0]['text']);
        $t->same('https://example.test/docs', $meta['pdfAppliedLinkAnnotations'][0]['uri']);
        $t->contains('<a href="https://example.test/docs">Read docs here.</a>', $blocks);
        $t->contains('<p>Plain coda.</p>', $blocks);
    },
    'uses pdf link annotation quadpoints before broad rectangles for visible text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Intro sentence.) Tj 0 -16 Td (Read docs here.) Tj 0 -16 Td (Plain coda.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 175 716] /QuadPoints [70 716 163 716 70 700 163 700] /A << /S /URI /URI (https://example.test/docs) >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[1]->type);
        $t->same('link', $document->children[1]->children[0]->type);
        $t->same('https://example.test/docs', $document->children[1]->children[0]->attr('url'));
        $t->same('Read docs here.', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same(1, count($meta['pdfLinkAnnotations']));
        $t->same('Read docs here.', $meta['pdfLinkAnnotations'][0]['text']);
        $t->same([70.0, 716.0, 163.0, 716.0, 70.0, 700.0, 163.0, 700.0], $meta['pdfLinkAnnotations'][0]['quadPoints']);
        $t->same(1, count($meta['pdfAppliedLinkAnnotations']));
        $t->contains('<a href="https://example.test/docs">Read docs here.</a>', $blocks);
        $t->contains('<p>Plain coda.</p>', $blocks);
        $t->true(!str_contains($blocks, 'Read docs here. Plain coda.'));
    },
    'carries pdf text annotation contents in metadata without body injection' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Reviewed passage.) Tj 0 -16 Td (Plain body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [70 716 190 735] /QuadPoints [70 735 190 735 70 716 190 716] /Contents 8 0 R /T 9 0 R /M (D:20260619120000Z) /Subj (Review note) >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [70 690 190 710] /Contents <FEFF005300650063006F006E00640020006E006F00740065> /NM (note-1) /Subj (Margin summary) >>\nendobj\n"
            . "8 0 obj\n(Needs follow-up)\nendobj\n"
            . "9 0 obj\n(Reviewer)\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Reviewed passage.</p>', $blocks);
        $t->contains('<p>Plain body.</p>', $blocks);
        $t->true(!str_contains($blocks, 'Needs follow-up'));
        $t->true(!str_contains($blocks, 'Second note'));
        $t->same(2, count($meta['pdfTextAnnotations']));
        $t->same('Highlight', $meta['pdfTextAnnotations'][0]['subtype']);
        $t->same('Needs follow-up', $meta['pdfTextAnnotations'][0]['contents']);
        $t->same('Reviewer', $meta['pdfTextAnnotations'][0]['title']);
        $t->same([70.0, 735.0, 190.0, 735.0, 70.0, 716.0, 190.0, 716.0], $meta['pdfTextAnnotations'][0]['quadPoints']);
        $t->same('FreeText', $meta['pdfTextAnnotations'][1]['subtype']);
        $t->same('Second note', $meta['pdfTextAnnotations'][1]['contents']);
        $t->same('note-1', $meta['pdfTextAnnotations'][1]['name']);
        $t->same($meta['pdfTextAnnotations'], $meta['pdfDiagnostics']['textAnnotations']);
    },
    'carries broader markup annotation metadata without body injection' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Markup body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [70 716 90 736] /Contents (Sticky note) /Name /Comment /Open true /C [1 0.5 0] /F 36 /CA 0.75 /CreationDate (D:20260619130000Z) /State (Accepted) /StateModel (Review) >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Ink /Rect [70 680 150 710] /Contents (Ink path) /InkList [[70 700 80 710 90 705] [100 700 110 708]] /Border [0 0 2] /BS << /W 2 /S /D /D [3 2] >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Line /Rect [70 640 150 690] /Contents (Line note) /L [70 660 140 680] /LE [/OpenArrow /ClosedArrow] /IC [0 1 0] /Cap true /LL 4 /LLE 2 /LLO 1 /IT /LineArrow >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Polygon /Rect [70 600 120 650] /Contents (Polygon note) /Vertices [70 620 90 640 110 620] /IC [0 0 1] /BE << /S /Cloudy /I 1.5 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Redact /Rect [70 560 170 585] /Contents (Redaction note) /OverlayText (Remove) /Repeat true /Q 1 /DA (/Helv 10 Tf 1 0 0 rg) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Markup body.</p>', $blocks);
        foreach (['Sticky note', 'Ink path', 'Line note', 'Polygon note', 'Redaction note'] as $annotationText) {
            $t->true(!str_contains($blocks, $annotationText));
        }
        $t->same(5, count($meta['pdfTextAnnotations']));
        $t->same('Text', $meta['pdfTextAnnotations'][0]['subtype']);
        $t->same('Comment', $meta['pdfTextAnnotations'][0]['iconName']);
        $t->same(['print', 'noView'], $meta['pdfTextAnnotations'][0]['flagNames']);
        $t->same(0.75, $meta['pdfTextAnnotations'][0]['opacity']);
        $t->same('Ink', $meta['pdfTextAnnotations'][1]['subtype']);
        $t->same([[70.0, 700.0, 80.0, 710.0, 90.0, 705.0], [100.0, 700.0, 110.0, 708.0]], $meta['pdfTextAnnotations'][1]['inkList']);
        $t->same([3.0, 2.0], $meta['pdfTextAnnotations'][1]['borderStyle']['dashPattern']);
        $t->same('Line', $meta['pdfTextAnnotations'][2]['subtype']);
        $t->same([70.0, 660.0, 140.0, 680.0], $meta['pdfTextAnnotations'][2]['line']);
        $t->same(['OpenArrow', 'ClosedArrow'], $meta['pdfTextAnnotations'][2]['lineEndingStyles']);
        $t->same('LineArrow', $meta['pdfTextAnnotations'][2]['intent']);
        $t->same('Polygon', $meta['pdfTextAnnotations'][3]['subtype']);
        $t->same('Cloudy', $meta['pdfTextAnnotations'][3]['borderEffect']['style']);
        $t->same('Redact', $meta['pdfTextAnnotations'][4]['subtype']);
        $t->same('Remove', $meta['pdfTextAnnotations'][4]['overlayText']);
        $t->same('/Helv 10 Tf 1 0 0 rg', $meta['pdfTextAnnotations'][4]['defaultAppearance']);
        $t->same($meta['pdfTextAnnotations'], $meta['pdfDiagnostics']['textAnnotations']);
    },
    'carries common markup subtype geometry metadata without body injection' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Variant body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R 13 0 R 14 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Underline /Rect [70 716 190 735] /QuadPoints [70 735 190 735 70 716 190 716] /Contents (Underline note) /C [1 0 0] /CA 0.6 >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Squiggly /Rect [70 690 190 710] /QuadPoints [70 710 190 710 70 690 190 690] /Contents (Squiggly note) /IRT 6 0 R /RT /Group /Popup 14 0 R /F 4 >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /StrikeOut /Rect [70 665 190 685] /QuadPoints [70 685 190 685 70 665 190 665] /Contents (Strike note) /StructParent 2 >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Square /Rect [70 625 150 660] /Contents (Square note) /RD [2 3 4 5] /IC [0.9 0.9 0.1] /BE << /S /Cloudy /I 2 >> /BS << /W 1.5 /S /D /D [2 1] >> /Rotate 90 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Circle /Rect [170 625 240 660] /Contents (Circle note) /RD [1 1 1 1] /Border [0 0 1] >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /PolyLine /Rect [70 585 150 620] /Contents (Polyline note) /Vertices [70 600 90 620 110 600 130 620] /LE [/None /OpenArrow] /IT /PolyLineDimension >>\nendobj\n"
            . "12 0 obj\n<< /Type /Annot /Subtype /Stamp /Rect [70 545 150 575] /Contents (Stamp note) /Name /Approved /StructParents 4 >>\nendobj\n"
            . "13 0 obj\n<< /Type /Annot /Subtype /Caret /Rect [170 545 190 575] /Contents (Caret note) /Sy /P /RD [0 0 5 0] >>\nendobj\n"
            . "14 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [220 650 360 720] /Parent 7 0 R /Open false >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Variant body.</p>', $blocks);
        foreach (['Underline note', 'Squiggly note', 'Strike note', 'Square note', 'Circle note', 'Polyline note', 'Stamp note', 'Caret note'] as $annotationText) {
            $t->true(!str_contains($blocks, $annotationText));
        }
        $t->same(8, count($meta['pdfTextAnnotations']));
        $t->same('Underline', $meta['pdfTextAnnotations'][0]['subtype']);
        $t->same([1.0, 0.0, 0.0], $meta['pdfTextAnnotations'][0]['color']);
        $t->same(0.6, $meta['pdfTextAnnotations'][0]['opacity']);
        $t->same('Squiggly', $meta['pdfTextAnnotations'][1]['subtype']);
        $t->same(6, $meta['pdfTextAnnotations'][1]['inReplyToAnnotationObject']);
        $t->same('Group', $meta['pdfTextAnnotations'][1]['replyType']);
        $t->same(14, $meta['pdfTextAnnotations'][1]['popupAnnotationObject']);
        $t->same('StrikeOut', $meta['pdfTextAnnotations'][2]['subtype']);
        $t->same(2, $meta['pdfTextAnnotations'][2]['structParent']);
        $t->same('Square', $meta['pdfTextAnnotations'][3]['subtype']);
        $t->same([2.0, 3.0, 4.0, 5.0], $meta['pdfTextAnnotations'][3]['rectDifferences']);
        $t->same('Cloudy', $meta['pdfTextAnnotations'][3]['borderEffect']['style']);
        $t->same(90, $meta['pdfTextAnnotations'][3]['rotation']);
        $t->same('Circle', $meta['pdfTextAnnotations'][4]['subtype']);
        $t->same([1.0, 1.0, 1.0, 1.0], $meta['pdfTextAnnotations'][4]['rectDifferences']);
        $t->same('PolyLine', $meta['pdfTextAnnotations'][5]['subtype']);
        $t->same(['None', 'OpenArrow'], $meta['pdfTextAnnotations'][5]['lineEndingStyles']);
        $t->same('PolyLineDimension', $meta['pdfTextAnnotations'][5]['intent']);
        $t->same('Stamp', $meta['pdfTextAnnotations'][6]['subtype']);
        $t->same('Approved', $meta['pdfTextAnnotations'][6]['iconName']);
        $t->same(4, $meta['pdfTextAnnotations'][6]['structParents']);
        $t->same('Caret', $meta['pdfTextAnnotations'][7]['subtype']);
        $t->same('P', $meta['pdfTextAnnotations'][7]['symbol']);
        $t->same([0.0, 0.0, 5.0, 0.0], $meta['pdfTextAnnotations'][7]['rectDifferences']);
        $t->same($meta['pdfTextAnnotations'], $meta['pdfDiagnostics']['textAnnotations']);
    },
    'carries pdf file attachment and popup annotations in metadata without body injection' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Attachment body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [70 716 92 738] /Contents (Attachment note) /T (Reviewer) /M (D:20260619123000Z) /NM (attach-1) /Subj (Source file) /Name /Paperclip /FS << /F (report.csv) /UF (report-unicode.csv) /Desc (Source spreadsheet) >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [100 650 260 720] /Parent 6 0 R /Open true >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Attachment body.</p>', $blocks);
        $t->true(!str_contains($blocks, 'Attachment note'));
        $t->true(!str_contains($blocks, 'Source spreadsheet'));
        $t->same([], $meta['pdfTextAnnotations']);
        $t->same(1, count($meta['pdfFileAttachmentAnnotations']));
        $t->same('FileAttachment', $meta['pdfFileAttachmentAnnotations'][0]['subtype']);
        $t->same('Attachment note', $meta['pdfFileAttachmentAnnotations'][0]['contents']);
        $t->same('Reviewer', $meta['pdfFileAttachmentAnnotations'][0]['title']);
        $t->same('report-unicode.csv', $meta['pdfFileAttachmentAnnotations'][0]['file']);
        $t->same('Source spreadsheet', $meta['pdfFileAttachmentAnnotations'][0]['fileSpecification']['description']);
        $t->same(1, count($meta['pdfPopupAnnotations']));
        $t->same('Popup', $meta['pdfPopupAnnotations'][0]['subtype']);
        $t->same(true, $meta['pdfPopupAnnotations'][0]['open']);
        $t->same(6, $meta['pdfPopupAnnotations'][0]['parentAnnotationObject']);
        $t->same('FileAttachment', $meta['pdfPopupAnnotations'][0]['parentSubtype']);
        $t->same('Attachment note', $meta['pdfPopupAnnotations'][0]['parentContents']);
        $t->same($meta['pdfFileAttachmentAnnotations'], $meta['pdfDiagnostics']['fileAttachmentAnnotations']);
        $t->same($meta['pdfPopupAnnotations'], $meta['pdfDiagnostics']['popupAnnotations']);
    },
    'carries pdf annotation appearance streams in metadata without body injection' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Appearance body.) Tj ET';
        $normalAppearance = 'BT /F1 12 Tf 0 10 Td (Normal appearance) Tj ET';
        $normalAppearanceCompressed = gzcompress($normalAppearance);
        $downAppearance = 'BT /F1 12 Tf 0 10 Td (Down Yes appearance) Tj ET';
        $offAppearance = 'BT /F1 12 Tf 0 10 Td (Off appearance) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [70 716 170 738] /Contents (Widget note) /T (Approval) /AS /Yes /AP << /N 8 0 R /D << /Yes 9 0 R /Off 10 0 R >> >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Matrix [1 0 0 1 0 0] /Resources << /Font << /F1 4 0 R >> >> /Filter /FlateDecode /Length " . strlen($normalAppearanceCompressed) . " >>\nstream\n{$normalAppearanceCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($downAppearance) . " >>\nstream\n{$downAppearance}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Appearance body.</p>', $blocks);
        $t->true(!str_contains($blocks, 'Normal appearance'));
        $t->true(!str_contains($blocks, 'Down Yes appearance'));
        $t->true(!str_contains($blocks, 'Off appearance'));
        $t->same(1, count($meta['pdfAppearanceAnnotations']));
        $t->same('Widget', $meta['pdfAppearanceAnnotations'][0]['subtype']);
        $t->same('Yes', $meta['pdfAppearanceAnnotations'][0]['appearanceState']);
        $t->same('Widget note', $meta['pdfAppearanceAnnotations'][0]['contents']);
        $t->same(3, count($meta['pdfAppearanceAnnotations'][0]['appearanceStreams']));
        $t->same('Normal appearance', $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][0]['text']);
        $t->same(['FlateDecode'], $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][0]['filters']);
        $t->same([0.0, 0.0, 100.0, 24.0], $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][0]['bbox']);
        $t->same('Yes', $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][1]['state']);
        $t->same('Down Yes appearance', $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][1]['text']);
        $t->same('Off', $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][2]['state']);
        $t->same('Off appearance', $meta['pdfAppearanceAnnotations'][0]['appearanceStreams'][2]['text']);
        $t->same($meta['pdfAppearanceAnnotations'], $meta['pdfDiagnostics']['appearanceAnnotations']);
    },
    'carries remote and launch pdf link actions without applying unsafe anchors' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Remote manual.) Tj 0 -16 Td (Launch installer.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 170 735] /A << /S /GoToR /F << /F (docs/manual.pdf) /UF (docs/manual-unicode.pdf) >> /D [2 /FitH 720] /NewWindow true >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 182 716] /A << /S /Launch /F 8 0 R /Win << /F (installer.exe) /P (/quiet) /D (C:/Downloads) /O (open) >> >> >>\nendobj\n"
            . "8 0 obj\n(installer.exe)\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<p>Remote manual.</p>', $blocks);
        $t->contains('<p>Launch installer.</p>', $blocks);
        $t->true(!str_contains($blocks, '<a '));
        $t->same(2, count($meta['pdfLinkAnnotations']));
        $t->same([], $meta['pdfAppliedLinkAnnotations']);
        $t->same('gotor', $meta['pdfLinkAnnotations'][0]['kind']);
        $t->same('', $meta['pdfLinkAnnotations'][0]['uri']);
        $t->same(false, $meta['pdfLinkAnnotations'][0]['safeToApply']);
        $t->same('docs/manual-unicode.pdf', $meta['pdfLinkAnnotations'][0]['remoteFile']);
        $t->same(3, $meta['pdfLinkAnnotations'][0]['remotePage']);
        $t->same('launch', $meta['pdfLinkAnnotations'][1]['kind']);
        $t->same('', $meta['pdfLinkAnnotations'][1]['uri']);
        $t->same(false, $meta['pdfLinkAnnotations'][1]['safeToApply']);
        $t->same('installer.exe', $meta['pdfLinkAnnotations'][1]['launchFile']);
        $t->same('/quiet', $meta['pdfLinkAnnotations'][1]['launchParameters']);
    },
    'carries broader report only pdf link actions without applying unsafe anchors' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Named action.) Tj 0 -16 Td (Script action.) Tj 0 -16 Td (Submit form.) Tj 0 -16 Td (Reset form.) Tj 0 -16 Td (Import data.) Tj 0 -16 Td (Hide controls.) Tj ET';
        $javascript = 'app.alert("Review");';
        $javascriptCompressed = gzcompress($javascript);
        if (!is_string($javascriptCompressed)) {
            throw new RuntimeException('Could not build compressed JavaScript fixture.');
        }
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 180 735] /A << /S /Named /N /NextPage >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 180 716] /A << /S /JavaScript /JS 12 0 R /Next << /S /Named /N /Print >> >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 180 700] /A << /S /SubmitForm /F << /F (https://example.test/post) /UF (https://example.test/post-unicode) /Desc (Form endpoint) >> /Fields [13 0 R (email)] /Flags 4 >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 668 180 684] /A << /S /ResetForm /Fields [(customer-name) 13 0 R] /Flags 1 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 652 180 668] /A << /S /ImportData /F (data.fdf) >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 636 180 652] /A << /S /Hide /T [(customer-field) 13 0 R] /H false >> >>\nendobj\n"
            . "12 0 obj\n<< /Filter /FlateDecode /Length " . strlen($javascriptCompressed) . " >>\nstream\n{$javascriptCompressed}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Subtype /Widget /T (customer-name) /FT /Tx /NM (customer-field) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        foreach (['Named action.', 'Script action.', 'Submit form.', 'Reset form.', 'Import data.', 'Hide controls.'] as $line) {
            $t->contains('<p>' . $line . '</p>', $blocks);
        }
        $t->true(!str_contains($blocks, '<a '));
        $t->same(6, count($meta['pdfLinkAnnotations']));
        $t->same([], $meta['pdfAppliedLinkAnnotations']);
        $t->same(['named', 'javascript', 'submitForm', 'resetForm', 'importData', 'hide'], array_column($meta['pdfLinkAnnotations'], 'kind'));
        foreach ($meta['pdfLinkAnnotations'] as $annotation) {
            $t->same('', $annotation['uri']);
            $t->same(false, $annotation['safeToApply']);
        }
        $t->same('NextPage', $meta['pdfLinkAnnotations'][0]['namedAction']);
        $t->same($javascript, $meta['pdfLinkAnnotations'][1]['javascript']);
        $t->same(['FlateDecode'], $meta['pdfLinkAnnotations'][1]['javascriptFilters']);
        $t->same('Print', $meta['pdfLinkAnnotations'][1]['nextActions'][0]['namedAction']);
        $t->same('https://example.test/post-unicode', $meta['pdfLinkAnnotations'][2]['submitFile']);
        $t->same('customer-name', $meta['pdfLinkAnnotations'][2]['fields'][0]['field']);
        $t->same('email', $meta['pdfLinkAnnotations'][2]['fields'][1]['field']);
        $t->same(1, $meta['pdfLinkAnnotations'][3]['flags']);
        $t->same('data.fdf', $meta['pdfLinkAnnotations'][4]['importFile']);
        $t->same(false, $meta['pdfLinkAnnotations'][5]['hide']);
        $t->same('customer-field', $meta['pdfLinkAnnotations'][5]['targets'][0]['target']);
        $t->same($meta['pdfLinkAnnotations'], $meta['pdfDiagnostics']['linkAnnotations']);
    },
    'carries pdf annotation additional actions without applying unsafe triggers' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Docs link.) Tj 0 -16 Td (Extra trigger.) Tj ET';
        $enterJavascript = 'app.alert("hover");';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 160 735] /A << /S /URI /URI (https://example.test/docs) >> /AA << /E << /S /JavaScript /JS <" . bin2hex($enterJavascript) . "> >> /X 12 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 170 716] /AA << /U << /S /SubmitForm /F (https://example.test/post) /Fields [13 0 R (email)] /Flags 8 >> /Bl << /S /ResetForm /Fields [(email)] /Flags 1 >> >> >>\nendobj\n"
            . "12 0 obj\n<< /S /Named /N /GoBack >>\nendobj\n"
            . "13 0 obj\n<< /Subtype /Widget /T (customer-name) /FT /Tx /NM (customer-field) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('<a href="https://example.test/docs">Docs link.</a>', $blocks);
        $t->contains('<p>Extra trigger.</p>', $blocks);
        $t->same(2, count($meta['pdfLinkAnnotations']));
        $t->same(1, count($meta['pdfAppliedLinkAnnotations']));
        $t->same('uri', $meta['pdfLinkAnnotations'][0]['kind']);
        $t->same(2, count($meta['pdfLinkAnnotations'][0]['additionalActions']));
        $t->same('E', $meta['pdfLinkAnnotations'][0]['additionalActions'][0]['trigger']);
        $t->same($enterJavascript, $meta['pdfLinkAnnotations'][0]['additionalActions'][0]['javascript']);
        $t->same('GoBack', $meta['pdfLinkAnnotations'][0]['additionalActions'][1]['namedAction']);
        $t->same($meta['pdfLinkAnnotations'][0]['additionalActions'], $meta['pdfAppliedLinkAnnotations'][0]['additionalActions']);
        $t->same('additionalActions', $meta['pdfLinkAnnotations'][1]['kind']);
        $t->same('', $meta['pdfLinkAnnotations'][1]['uri']);
        $t->same(false, $meta['pdfLinkAnnotations'][1]['safeToApply']);
        $t->same('SubmitForm', $meta['pdfLinkAnnotations'][1]['additionalActions'][0]['action']);
        $t->same(8, $meta['pdfLinkAnnotations'][1]['additionalActions'][0]['flags']);
        $t->same('customer-name', $meta['pdfLinkAnnotations'][1]['additionalActions'][0]['fields'][0]['field']);
        $t->same('ResetForm', $meta['pdfLinkAnnotations'][1]['additionalActions'][1]['action']);
        $t->same($meta['pdfLinkAnnotations'], $meta['pdfDiagnostics']['linkAnnotations']);
    },
    'carries additional standard report only pdf link actions without applying unsafe anchors' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Embedded jump.) Tj 0 -16 Td (Thread jump.) Tj 0 -16 Td (Sound cue.) Tj 0 -16 Td (Movie action.) Tj 0 -16 Td (Rendition action.) Tj 0 -16 Td (Transition action.) Tj 0 -16 Td (Layer toggle.) Tj 0 -16 Td (View action.) Tj 0 -16 Td (Rich media.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R 13 0 R 14 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 180 735] /A << /S /GoToE /F << /F (embedded.pdf) /UF (embedded-unicode.pdf) >> /D [0 /Fit] /T << /R /C /N (chapter.pdf) >> /NewWindow false >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 170 716] /A << /S /Thread /D 15 0 R /B 16 0 R >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 150 700] /A << /S /Sound /Sound 17 0 R /Volume 0.5 /Synchronous true /Repeat false /Mix true >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 668 170 684] /A << /S /Movie /Annotation 18 0 R /Operation /Play >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 652 190 668] /A << /S /Rendition /OP 0 /R 19 0 R /AN 20 0 R /JS (reviewRendition()) >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 636 190 652] /A << /S /Trans /Trans << /S /Dissolve /D 1.5 /Dm /H /M /I >> >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 620 170 636] /A << /S /SetOCGState /State [/ON 21 0 R /Toggle 22 0 R /OFF (Layer A)] /PreserveRB false >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 604 160 620] /A << /S /GoTo3DView /TA 23 0 R /V /DefaultView >> >>\nendobj\n"
            . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 588 160 604] /A << /S /RichMediaExecute /TA 24 0 R /CMD << /C (play) /Args [(scene)] >> >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /Thread /I (article-thread) >>\nendobj\n"
            . "16 0 obj\n<< /Type /Bead /T 15 0 R >>\nendobj\n"
            . "17 0 obj\n<< /R 44100 /C 2 /B 16 /E /Signed >>\nendobj\n"
            . "18 0 obj\n<< /Subtype /Movie /T (Trailer clip) >>\nendobj\n"
            . "19 0 obj\n<< /S /MR /N (Rendition Name) >>\nendobj\n"
            . "20 0 obj\n<< /Subtype /Screen /NM (screen-one) >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Layer One) >>\nendobj\n"
            . "22 0 obj\n<< /Type /OCG /Name (Layer Two) >>\nendobj\n"
            . "23 0 obj\n<< /Subtype /3D /NM (model) >>\nendobj\n"
            . "24 0 obj\n<< /Subtype /RichMedia /NM (rich-media) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        foreach (['Embedded jump.', 'Thread jump.', 'Sound cue.', 'Movie action.', 'Rendition action.', 'Transition action.', 'Layer toggle.', 'View action.', 'Rich media.'] as $line) {
            $t->contains('<p>' . $line . '</p>', $blocks);
        }
        $t->true(!str_contains($blocks, '<a '));
        $t->same(9, count($meta['pdfLinkAnnotations']));
        $t->same([], $meta['pdfAppliedLinkAnnotations']);
        $t->same(['gotoe', 'thread', 'sound', 'movie', 'rendition', 'transition', 'setOCGState', 'goto3DView', 'richMediaExecute'], array_column($meta['pdfLinkAnnotations'], 'kind'));
        foreach ($meta['pdfLinkAnnotations'] as $annotation) {
            $t->same('', $annotation['uri']);
            $t->same(false, $annotation['safeToApply']);
        }
        $t->same('embedded-unicode.pdf', $meta['pdfLinkAnnotations'][0]['embeddedFile']);
        $t->same('chapter.pdf', $meta['pdfLinkAnnotations'][0]['target']['name']);
        $t->same(15, $meta['pdfLinkAnnotations'][1]['threads'][0]['objectNumber']);
        $t->same(44100.0, $meta['pdfLinkAnnotations'][2]['sound']['rate']);
        $t->same('Play', $meta['pdfLinkAnnotations'][3]['operation']);
        $t->same('reviewRendition()', $meta['pdfLinkAnnotations'][4]['javascript']);
        $t->same('Dissolve', $meta['pdfLinkAnnotations'][5]['transition']['style']);
        $t->same('Layer One', $meta['pdfLinkAnnotations'][6]['state'][1]['name']);
        $t->same('DefaultView', $meta['pdfLinkAnnotations'][7]['view']);
        $t->same('play', $meta['pdfLinkAnnotations'][8]['command']['command']);
        $t->same($meta['pdfLinkAnnotations'], $meta['pdfDiagnostics']['linkAnnotations']);
    },
    'uses pdf internal link annotations for visible non url text' => static function (TestRunner $t): void {
        $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Jump to Details.) Tj ET';
        $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Details paragraph.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 164 735] /A << /S /GoTo /D [7 0 R /Fit] >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('link', $document->children[0]->children[0]->type);
        $t->same('#pdf-page-2', $document->children[0]->children[0]->attr('url'));
        $t->same('Jump to Details.', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same(1, count($meta['pdfLinkAnnotations']));
        $t->same(1, count($meta['pdfAppliedLinkAnnotations']));
        $t->same('goto', $meta['pdfLinkAnnotations'][0]['kind']);
        $t->same(2, $meta['pdfLinkAnnotations'][0]['targetPage']);
        $t->same('#pdf-page-2', $meta['pdfAppliedLinkAnnotations'][0]['uri']);
        $t->contains('<a href="#pdf-page-2">Jump to Details.</a>', $blocks);
        $t->contains('<p>Details paragraph.</p>', $blocks);
    },
    'uses pdf named destination link annotations for visible non url text' => static function (TestRunner $t): void {
        $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Named Details.) Tj ET';
        $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named destination target.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Dests << /DetailsDest [7 0 R /Fit] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 155 735] /Dest /DetailsDest >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('link', $document->children[0]->children[0]->type);
        $t->same('#pdf-page-2', $document->children[0]->children[0]->attr('url'));
        $t->same('Named Details.', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same(1, count($meta['pdfLinkAnnotations']));
        $t->same(1, count($meta['pdfAppliedLinkAnnotations']));
        $t->same('goto', $meta['pdfLinkAnnotations'][0]['kind']);
        $t->same('Fit', $meta['pdfLinkAnnotations'][0]['destinationType']);
        $t->same(2, $meta['pdfAppliedLinkAnnotations'][0]['targetPage']);
        $t->contains('<a href="#pdf-page-2">Named Details.</a>', $blocks);
        $t->contains('<p>Named destination target.</p>', $blocks);
    },
    'reads LZW encoded pdf streams into pandoc ast blocks' => static function (TestRunner $t) use ($lzwEncode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (LZW PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $encoded = $lzwEncode($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('LZW PDF Import', $blocks);
        $t->contains('Clean WordPress Blocks', $blocks);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfDiagnostics']['failedStreams']);
    },
    'reads Crypt Identity filtered pdf streams into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Crypt Identity PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] /Length " . strlen($compressed) . " >>\n"
            . "stream\n{$compressed}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('Crypt Identity PDF Import', $blocks);
        $t->contains('Clean WordPress Blocks', $blocks);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfDiagnostics']['failedStreams']);
    },
    'reads escaped pdf stream filter names into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Escaped Filter PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $encoded = strtoupper(bin2hex(gzcompress($content))) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /ASCIIHex#44ecode /Flate#44ecode ] /Length " . strlen($encoded) . " >>\n"
            . "stream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('Escaped Filter PDF Import', $blocks);
        $t->contains('Clean WordPress Blocks', $blocks);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfDiagnostics']['failedStreams']);
    },
    'reads wrapped ASCII85 pdf streams into pandoc ast blocks' => static function (TestRunner $t) use ($ascii85Encode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Wrapped ASCII85 PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $encoded = '<~' . $ascii85Encode($content);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($encoded) . " >>\n"
            . "stream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('Wrapped ASCII85 PDF Import', $blocks);
        $t->contains('Clean WordPress Blocks', $blocks);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfDiagnostics']['failedStreams']);
    },
    'records pdf structural provenance and info metadata in the pandoc ast' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Metadata body) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R /Contents 4 0 R >> endobj\n"
            . '4 0 obj << /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj << /Title (PDF Metadata Demo) /Author (Port Libs) /Creator (Unit Test) /Producer (markerPDF) /CreationDate (D:20260618000000Z) >> endobj\n"
            . "trailer << /Root 1 0 R /Info 5 0 R >>\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $meta = $document->attr('meta');

        $t->same('%PDF-1.4', $meta['pdfHeader']);
        $t->same(1, $meta['pdfEstimatedPages']);
        $t->same(5, $meta['pdfObjectCount']);
        $t->same(1, $meta['pdfStreamCount']);
        $t->same(false, $meta['pdfEncrypted']);
        $t->same('PDF Metadata Demo', $meta['title']);
        $t->same('PDF Metadata Demo', $meta['titleInlines'][0]->attr('text'));
        $t->same('Port Libs', $meta['author']);
        $t->same('Unit Test', $meta['creator']);
        $t->same('markerPDF', $meta['producer']);
        $t->same('D:20260618000000Z', $meta['created']);
    },
    'records tagged pdf role map and language semantics in pandoc metadata' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Lang (en-US) /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Lang (fr-CA) /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /Lang (en-GB) /RoleMap 13 0 R /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /FancyHeading /Lang (de-DE) /ActualText (Role Mapped Heading) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /CustomParagraph /ActualText (Role Mapped Paragraph) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /NestedRole /Lang 12 0 R /ActualText (Role Mapped Nested) >>\nendobj\n"
            . "12 0 obj\n(es-MX)\nendobj\n"
            . "13 0 obj\n<< /FancyHeading /H1 /CustomParagraph /P /NestedRole /FancyHeading >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->contains('Role Mapped Heading', $blocks);
        $t->contains('Role Mapped Paragraph', $blocks);
        $t->contains('Role Mapped Nested', $blocks);
        $t->same([
            'CustomParagraph' => 'P',
            'FancyHeading' => 'H1',
            'NestedRole' => 'FancyHeading',
        ], $meta['pdfTaggedRoleMap']);
        $t->same([
            'CustomParagraph' => 'P',
            'FancyHeading' => 'H1',
            'NestedRole' => 'H1',
        ], $meta['pdfTaggedStructureRoles']);
        $t->same(['de-DE', 'en-GB', 'en-US', 'es-MX', 'fr-CA'], $meta['pdfTaggedStructureLanguages']);
        $t->same(3, $meta['pdfTaggedStructElementCount']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'records tagged pdf ClassMap and attribute semantics in pandoc metadata' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /RoleMap 13 0 R /ClassMap 14 0 R /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /FancyHeading /Lang (fr-CA) /C /CaptionClass /A 15 0 R /ActualText (Attribute Heading) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /CustomParagraph /Lang (es-MX) /C [ /ListClass ] /A [ 16 0 R << /O /Layout /SpaceAfter 4 >> ] /ActualText (Attribute Paragraph) >>\nendobj\n"
            . "13 0 obj\n<< /FancyHeading /H1 /CustomParagraph /P >>\nendobj\n"
            . "14 0 obj\n<< /CaptionClass << /O /Layout /Placement /Block /SpaceBefore 6 >> /ListClass [ << /O /List /ListNumbering /Decimal >> 0 << /O /Layout /WritingMode /LrTb >> ] >>\nendobj\n"
            . "15 0 obj\n<< /O /Layout /Placement /Block /SpaceAfter 2 /BBox [0 0 200 24] >>\nendobj\n"
            . "16 0 obj\n<< /O /Table /RowSpan 2 /ColSpan 3 >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $headingHtmlAttrs = $heading->attr('htmlAttributes', []);
        $paragraphHtmlAttrs = $paragraph->attr('htmlAttributes', []);

        $t->same('heading', $heading->type);
        $t->same('paragraph', $paragraph->type);
        $t->same(['pdf-class-caption-class'], $heading->attr('classes'));
        $t->same('fr-CA', $headingHtmlAttrs['lang'] ?? null);
        $t->same('FancyHeading', $headingHtmlAttrs['data-pdf-role'] ?? null);
        $t->same('H1', $headingHtmlAttrs['data-pdf-resolved-role'] ?? null);
        $t->same('9', $headingHtmlAttrs['data-pdf-struct-object'] ?? null);
        $t->same('CaptionClass', $headingHtmlAttrs['data-pdf-classes'] ?? null);
        $t->same('Block', $headingHtmlAttrs['data-pdf-layout-placement'] ?? null);
        $t->same('6', $headingHtmlAttrs['data-pdf-layout-space-before'] ?? null);
        $t->same('2', $headingHtmlAttrs['data-pdf-layout-space-after'] ?? null);
        $t->same('0 0 200 24', $headingHtmlAttrs['data-pdf-layout-bbox'] ?? null);
        $t->same('pdf-class-caption-class', $headingHtmlAttrs['class'] ?? null);
        $t->same(['pdf-class-list-class'], $paragraph->attr('classes'));
        $t->same('es-MX', $paragraphHtmlAttrs['lang'] ?? null);
        $t->same('CustomParagraph', $paragraphHtmlAttrs['data-pdf-role'] ?? null);
        $t->same('P', $paragraphHtmlAttrs['data-pdf-resolved-role'] ?? null);
        $t->same('10', $paragraphHtmlAttrs['data-pdf-struct-object'] ?? null);
        $t->same('ListClass', $paragraphHtmlAttrs['data-pdf-classes'] ?? null);
        $t->same('Decimal', $paragraphHtmlAttrs['data-pdf-list-list-numbering'] ?? null);
        $t->same('LrTb', $paragraphHtmlAttrs['data-pdf-layout-writing-mode'] ?? null);
        $t->same('4', $paragraphHtmlAttrs['data-pdf-layout-space-after'] ?? null);
        $t->same('2', $paragraphHtmlAttrs['data-pdf-table-row-span'] ?? null);
        $t->same('3', $paragraphHtmlAttrs['data-pdf-table-col-span'] ?? null);
        $t->same('pdf-class-list-class', $paragraphHtmlAttrs['class'] ?? null);
        $t->contains('Attribute Heading', $blocks);
        $t->contains('Attribute Paragraph', $blocks);
        $t->contains('<h1 lang="fr-CA"', $blocks);
        $t->contains('data-pdf-role="FancyHeading"', $blocks);
        $t->contains('data-pdf-resolved-role="H1"', $blocks);
        $t->contains('data-pdf-struct-object="9"', $blocks);
        $t->contains('data-pdf-layout-space-before="6"', $blocks);
        $t->contains('data-pdf-layout-bbox="0 0 200 24"', $blocks);
        $t->contains('class="pdf-class-caption-class">Attribute Heading</h1>', $blocks);
        $t->contains('<p lang="es-MX"', $blocks);
        $t->contains('data-pdf-role="CustomParagraph"', $blocks);
        $t->contains('data-pdf-list-list-numbering="Decimal"', $blocks);
        $t->contains('data-pdf-table-col-span="3"', $blocks);
        $t->contains('class="pdf-class-list-class">Attribute Paragraph</p>', $blocks);
        $t->same([
            'CaptionClass' => [
                [
                    'O' => 'Layout',
                    'Placement' => 'Block',
                    'SpaceBefore' => 6,
                ],
            ],
            'ListClass' => [
                [
                    'ListNumbering' => 'Decimal',
                    'O' => 'List',
                ],
                [
                    'O' => 'Layout',
                    'WritingMode' => 'LrTb',
                ],
            ],
        ], $meta['pdfTaggedClassMap']);
        $t->same([
            [
                'role' => 'FancyHeading',
                'resolvedRole' => 'H1',
                'classes' => ['CaptionClass'],
                'attributes' => [
                    [
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceBefore' => 6,
                    ],
                    [
                        'BBox' => [0, 0, 200, 24],
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceAfter' => 2,
                    ],
                ],
            ],
            [
                'role' => 'CustomParagraph',
                'resolvedRole' => 'P',
                'classes' => ['ListClass'],
                'attributes' => [
                    [
                        'ListNumbering' => 'Decimal',
                        'O' => 'List',
                    ],
                    [
                        'O' => 'Layout',
                        'WritingMode' => 'LrTb',
                    ],
                    [
                        'ColSpan' => 3,
                        'O' => 'Table',
                        'RowSpan' => 2,
                    ],
                    [
                        'O' => 'Layout',
                        'SpaceAfter' => 4,
                    ],
                ],
            ],
        ], $meta['pdfTaggedStructureAttributes']);
        $t->same(['es-MX', 'fr-CA'], $meta['pdfTaggedStructureLanguages']);
        $t->same(['Layout', 'List', 'Table'], $meta['pdfTaggedAttributeOwners']);
        $t->same(2, $meta['pdfTaggedStructElementCount']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses tagged pdf roles and list attributes for semantic ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /RoleMap 14 0 R /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R 12 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /CustomHeading /ActualText (Semantic Heading.) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /P /ActualText (Body paragraph.) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /LI /A << /O /List /ListNumbering /Decimal >> /ActualText (First semantic item.) >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /LI /A << /O /List /ListNumbering /Decimal >> /ActualText (Second semantic item.) >>\nendobj\n"
            . "14 0 obj\n<< /CustomHeading /H1 >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Semantic Heading.', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('Body paragraph.', $document->children[1]->attr('text'));
        $t->same('ordered_list', $document->children[2]->type);
        $t->same('First semantic item.', $document->children[2]->children[0]->children[0]->attr('text'));
        $t->same('Second semantic item.', $document->children[2]->children[1]->children[0]->attr('text'));
        $t->same(4, $meta['pdfTaggedStructElementCount']);
        $t->same('Semantic Heading.', $meta['pdfTaggedStructureItems'][0]['text']);
        $t->same('H1', $meta['pdfTaggedStructureItems'][0]['resolvedRole']);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses tagged pdf table roles for semantic table ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 13 0 R 14 0 R 15 0 R 16 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /TR /K [13 0 R 14 0 R] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /TR /K [15 0 R 16 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 /RowSpan 1 >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same('Name', $document->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Count', $document->children[0]->children[0]->children[0]->children[1]->attr('text'));
        $t->same('Apples', $document->children[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('3', $document->children[0]->children[1]->children[0]->children[1]->attr('text'));
        $t->same(2, $document->children[0]->children[1]->children[0]->children[1]->attr('colspan'));
        $t->same('3', $meta['pdfTaggedTables'][0]['rows'][1][1]['text']);
        $t->same(2, $meta['pdfTaggedTables'][0]['rows'][1][1]['colSpan']);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th data-pdf-role="TH" data-pdf-resolved-role="TH">Name</th>', $blocks);
        $t->contains('<th data-pdf-role="TH" data-pdf-resolved-role="TH">Count</th>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD">Apples</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" data-pdf-table-row-span="1" colspan="2">3</td>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses tagged pdf table sections for semantic table ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 16 0 R 17 0 R 18 0 R 19 0 R 20 0 R 21 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /THead /K 13 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /TBody /K 14 0 R >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /TFoot /K 15 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TR /K [20 0 R 21 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ID (name-h) /A << /O /Table /Scope /Column >> /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ID (count-h) /A << /O /Table /Scope /Column >> /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /Headers [(name-h)] >> /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 /Headers [(count-h)] >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /Scope /Row >> /ActualText (Total) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /TD /ActualText (3) /K << /Type /MCR /MCID 5 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $table = $document->children[0];

        $t->same('table', $table->type);
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
        $t->same('Name', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Apples', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Total', $table->children[2]->children[0]->children[0]->attr('text'));
        $t->same(true, $table->children[2]->children[0]->children[0]->attr('header'));
        $t->same('col', $table->children[0]->children[0]->children[0]->attr('attributes')['scope'] ?? null);
        $t->same('row', $table->children[2]->children[0]->children[0]->attr('attributes')['scope'] ?? null);
        $t->same('pdf-name-h', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['id'] ?? null);
        $t->same('pdf-count-h', $table->children[0]->children[0]->children[1]->attr('htmlAttributes')['id'] ?? null);
        $t->same('pdf-name-h', $table->children[1]->children[0]->children[0]->attr('htmlAttributes')['headers'] ?? null);
        $t->same('pdf-count-h', $table->children[1]->children[0]->children[1]->attr('htmlAttributes')['headers'] ?? null);
        $t->same(['THead', 'TBody', 'TFoot'], array_column($meta['pdfTaggedTables'][0]['sections'], 'role'));
        $t->same('Total', $meta['pdfTaggedTables'][0]['sections'][2]['rows'][0][0]['text']);
        $t->contains('<thead data-pdf-role="THead" data-pdf-resolved-role="THead" data-pdf-struct-object="10">', $blocks);
        $t->contains('<tbody data-pdf-role="TBody" data-pdf-resolved-role="TBody" data-pdf-struct-object="11">', $blocks);
        $t->contains('<tfoot data-pdf-role="TFoot" data-pdf-resolved-role="TFoot" data-pdf-struct-object="12">', $blocks);
        $t->contains('<th id="pdf-name-h" data-pdf-role="TH" data-pdf-resolved-role="TH" data-pdf-struct-id="name-h" data-pdf-table-scope="Column" scope="col">Name</th>', $blocks);
        $t->contains('<th id="pdf-count-h" data-pdf-role="TH" data-pdf-resolved-role="TH" data-pdf-struct-id="count-h" data-pdf-table-scope="Column" scope="col">Count</th>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-headers="name-h" headers="pdf-name-h">Apples</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" data-pdf-table-headers="count-h" headers="pdf-count-h" colspan="2">3</td>', $blocks);
        $t->contains('<th data-pdf-role="TH" data-pdf-resolved-role="TH" data-pdf-table-scope="Row" scope="row">Total</th>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses tagged pdf table sections and cells through neutral wrappers' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 6 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 7 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /RoleMap << /HeaderBand /THead /BodyBand /TBody /SummaryBand /TFoot >> /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 20 0 R 21 0 R 22 0 R 23 0 R 24 0 R 25 0 R 26 0 R 27 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Div /K 13 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /NonStruct /K [14 0 R 15 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /SummaryBand /K 18 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /HeaderBand /K 16 0 R >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /BodyBand /K 17 0 R >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /BodyBand /K 19 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TR /K [20 0 R 21 0 R] >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TR /K [22 0 R 23 0 R] >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TR /K [26 0 R 27 0 R] >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TR /K 28 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "23 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "24 0 obj\n<< /Type /StructElem /S /TD /ActualText (Oranges) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "25 0 obj\n<< /Type /StructElem /S /TD /ActualText (5) /K << /Type /MCR /MCID 5 >> >>\nendobj\n"
            . "26 0 obj\n<< /Type /StructElem /S /TH /ActualText (Total) /K << /Type /MCR /MCID 6 >> >>\nendobj\n"
            . "27 0 obj\n<< /Type /StructElem /S /TD /ActualText (8) /K << /Type /MCR /MCID 7 >> >>\nendobj\n"
            . "28 0 obj\n<< /Type /StructElem /S /Div /K [24 0 R 25 0 R] >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $table = $document->children[0];

        $t->same('table', $table->type);
        $t->same(['table_head', 'table_body', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
        $t->same('Name', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Apples', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Oranges', $table->children[2]->children[0]->children[0]->attr('text'));
        $t->same('Total', $table->children[3]->children[0]->children[0]->attr('text'));
        $t->same(['HeaderBand', 'BodyBand', 'BodyBand', 'SummaryBand'], array_column($meta['pdfTaggedTables'][0]['sections'], 'role'));
        $t->same(['THead', 'TBody', 'TBody', 'TFoot'], array_column($meta['pdfTaggedTables'][0]['sections'], 'resolvedRole'));
        $t->contains('<thead data-pdf-role="HeaderBand" data-pdf-resolved-role="THead" data-pdf-struct-object="13">', $blocks);
        $t->contains('<tbody data-pdf-role="BodyBand" data-pdf-resolved-role="TBody" data-pdf-struct-object="14">', $blocks);
        $t->contains('<tbody data-pdf-role="BodyBand" data-pdf-resolved-role="TBody" data-pdf-struct-object="15">', $blocks);
        $t->contains('<tfoot data-pdf-role="SummaryBand" data-pdf-resolved-role="TFoot" data-pdf-struct-object="12">', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD">Oranges</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" colspan="2">3</td>', $blocks);
        $t->contains('<th data-pdf-role="TH" data-pdf-resolved-role="TH">Total</th>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses mixed tagged pdf block and table order for semantic ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 6 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K [9 0 R 10 0 R 11 0 R 18 0 R] >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 14 0 R 15 0 R 16 0 R 17 0 R 18 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Mixed Heading) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /P /ActualText (Intro paragraph.) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Table /K [12 0 R 13 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /TR /K [14 0 R 15 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 5 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /P /ActualText (Closing paragraph.) /K << /Type /MCR /MCID 6 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('heading', $document->children[0]->type);
        $t->same('Mixed Heading', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('Intro paragraph.', $document->children[1]->attr('text'));
        $t->same('table', $document->children[2]->type);
        $t->same('Apples', $document->children[2]->children[1]->children[0]->children[0]->attr('text'));
        $t->same(2, $document->children[2]->children[1]->children[0]->children[1]->attr('colspan'));
        $t->same('paragraph', $document->children[3]->type);
        $t->same('Closing paragraph.', $document->children[3]->attr('text'));
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same('table', $meta['pdfTaggedStructureBlocks'][2]['kind']);
        $t->same('3', $meta['pdfTaggedStructureBlocks'][2]['rows'][1][1]['text']);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<p data-pdf-role="P" data-pdf-resolved-role="P" data-pdf-struct-object="10">Intro paragraph.</p>', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD">Apples</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" colspan="2">3</td>', $blocks);
        $t->contains('<p data-pdf-role="P" data-pdf-resolved-role="P" data-pdf-struct-object="18">Closing paragraph.</p>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses partial nested tagged pdf structure while preserving untagged lines' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "(Untagged preface.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <3F314749314541> Tj EMC T* "
            . "/F2 12 Tf (Untagged coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 11 0 R 16 0 R 17 0 R 18 0 R 19 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Document /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Sect /K [11 0 R 12 0 R] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Nested Heading) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /Table /K [13 0 R 14 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 4 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Untagged preface.', $document->children[0]->attr('text'));
        $t->same('heading', $document->children[1]->type);
        $t->same('Nested Heading', $document->children[1]->attr('text'));
        $t->same('table', $document->children[2]->type);
        $t->same('Apples', $document->children[2]->children[1]->children[0]->children[0]->attr('text'));
        $t->same(2, $document->children[2]->children[1]->children[0]->children[1]->attr('colspan'));
        $t->same('paragraph', $document->children[3]->type);
        $t->same('Untagged coda.', $document->children[3]->attr('text'));
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same(2, count($meta['pdfTaggedStructureBlocks']));
        $t->same('Nested Heading', $meta['pdfTaggedStructureBlocks'][0]['text']);
        $t->same('table', $meta['pdfTaggedStructureBlocks'][1]['kind']);
        $t->same('3', $meta['pdfTaggedStructureBlocks'][1]['rows'][1][1]['text']);
        $t->contains('<p>Untagged preface.</p>', $blocks);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD">Apples</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" colspan="2">3</td>', $blocks);
        $t->contains('<p>Untagged coda.</p>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'falls back when partial tagged pdf structure text matches duplicate extracted lines' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "(Repeated paragraph.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/F2 12 Tf (Untagged coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Repeated paragraph.) /K << /Type /MCR /MCID 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(3, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('paragraph', $document->children[2]->type);
        $t->same('Repeated paragraph.', $document->children[0]->attr('text'));
        $t->same('Repeated paragraph.', $document->children[1]->attr('text'));
        $t->same('Untagged coda.', $document->children[2]->attr('text'));
        $t->same('H1', $meta['pdfTaggedStructureBlocks'][0]['resolvedRole']);
        $t->same('Repeated paragraph.', $meta['pdfTaggedStructureBlocks'][0]['text']);
        $t->contains('<p>Repeated paragraph.</p>', $blocks);
        $t->contains('<p>Untagged coda.</p>', $blocks);
        $t->true(!str_contains($blocks, '<!-- wp:heading'));
        $t->true(!str_contains($blocks, '?PKEA'));
    },
    'uses multi page tagged pdf structure while preserving untagged page gaps' => static function (TestRunner $t): void {
        $pageOneContent = "BT /F2 12 Tf 72 720 Td "
            . "(Page one preface.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pageTwoContent = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F314749314541> Tj EMC T* "
            . "/F2 12 Tf (Page two coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 9 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 1 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /StructTreeRoot /ParentTree 10 0 R /K 11 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Nums [ 0 [ 12 0 R ] 1 [ 16 0 R 17 0 R 18 0 R 19 0 R ] ] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Document /K [12 0 R 13 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Page One Heading) /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /Table /K [14 0 R 15 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /Pg 7 0 R /MCID 0 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /Pg 7 0 R /MCID 1 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /ActualText (Bananas) /K << /Type /MCR /Pg 7 0 R /MCID 2 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (7) /K << /Type /MCR /Pg 7 0 R /MCID 3 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Page one preface.', $document->children[0]->attr('text'));
        $t->same('heading', $document->children[1]->type);
        $t->same('Page One Heading', $document->children[1]->attr('text'));
        $t->same('table', $document->children[2]->type);
        $t->same('Bananas', $document->children[2]->children[1]->children[0]->children[0]->attr('text'));
        $t->same(2, $document->children[2]->children[1]->children[0]->children[1]->attr('colspan'));
        $t->same('paragraph', $document->children[3]->type);
        $t->same('Page two coda.', $document->children[3]->attr('text'));
        $t->same(2, $meta['pdfEstimatedPages']);
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same(2, count($meta['pdfTaggedStructureBlocks']));
        $t->same('Page One Heading', $meta['pdfTaggedStructureBlocks'][0]['text']);
        $t->same('table', $meta['pdfTaggedStructureBlocks'][1]['kind']);
        $t->same('7', $meta['pdfTaggedStructureBlocks'][1]['rows'][1][1]['text']);
        $t->contains('<p>Page one preface.</p>', $blocks);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<h1 data-pdf-role="H1" data-pdf-resolved-role="H1" data-pdf-struct-object="12">Page One Heading</h1>', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD">Bananas</td>', $blocks);
        $t->contains('<td data-pdf-role="TD" data-pdf-resolved-role="TD" data-pdf-table-col-span="2" colspan="2">7</td>', $blocks);
        $t->contains('<p>Page two coda.</p>', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'records pdf extraction diagnostics in pandoc metadata' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Diagnostic Text) Tj ET';
        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Length " . strlen($content) . " /Filter /DCTDecode >>\nstream\n{$content}\nendstream\nendobj\n"
            . "trailer << /Encrypt 9 0 R >>\nstartxref\n999999\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfDiagnostics']['encrypted']);
        $t->same(['DCTDecode'], $meta['pdfUnsupportedFilters']);
        $t->same([999999], $meta['pdfMalformedXrefOffsets']);
        $t->contains('Unsupported PDF stream filters: DCTDecode.', $warningText);
        $t->contains('Malformed PDF xref data was detected.', $warningText);
    },
    'reads Standard R2 empty-password encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent): void {
        $pdf = $standardR2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted PDF Text) Tj T* (Second encrypted line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted PDF Text', $blocks);
        $t->contains('Second encrypted line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R2-empty-user-password', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R2-empty-user-password', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R3 128-bit empty-password RC4 encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR3EncryptedPdfWithContent): void {
        $pdf = $standardR3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R3 PDF Text) Tj T* (Second R3 encrypted line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R3 PDF Text', $blocks);
        $t->contains('Second R3 encrypted line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R3-empty-user-password-RC4', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R3-empty-user-password-RC4', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R4 V2 crypt-filter encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR4V2EncryptedPdfWithContent): void {
        $pdf = $standardR4V2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 PDF Text) Tj T* (Second R4 crypt filter line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 PDF Text', $blocks);
        $t->contains('Second R4 crypt filter line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-RC4', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-RC4', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R4 mixed V2 stream and Identity string encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR4V2EncryptedPdfWithContent): void {
        $pdf = $standardR4V2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 mixed stream Text) Tj T* (Identity string filter line) Tj ET', 'StdCF', 'Identity');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 mixed stream Text', $blocks);
        $t->contains('Identity string filter line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-mixed-crypt-filters', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-mixed-crypt-filters', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R4 AESV2 crypt-filter encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $pdf = $standardR4AesV2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 AES PDF Text) Tj T* (Second R4 AES crypt filter line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 AES PDF Text', $blocks);
        $t->contains('Second R4 AES crypt filter line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R5 AESV3 crypt-filter encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR5AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R5 AES PDF Text) Tj T* (Second R5 AES crypt filter line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R5 AES PDF Text', $blocks);
        $t->contains('Second R5 AES crypt filter line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R6 AESV3 crypt-filter encrypted pdf text into pandoc ast blocks' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 AES PDF Text) Tj T* (Second R6 AES crypt filter line) Tj ET');
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 AES PDF Text', $blocks);
        $t->contains('Second R6 AES crypt filter line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'records Standard R6 AESV3 no-copy permission policy in pandoc metadata' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 no copy Text) Tj T* (Permission policy line) Tj ET', 'StdCF', 'StdCF', '', '', false, false, -20);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 no copy Text', $blocks);
        $t->contains('Permission policy line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same('empty-user-password', $meta['pdfEncryptionPasswordType']);
        $t->same(false, $meta['pdfEncryptionPermissions']['copy']);
        $t->same(4294967276, $meta['pdfEncryptionPermissions']['unsigned']);
        $t->same(false, $meta['pdfEncryptionAllowsContentExtraction']);
        $t->same(false, $meta['pdfDiagnostics']['encryptionPermissions']['copy']);
        $t->same(false, $meta['pdfDiagnostics']['encryptionAllowsContentExtraction']);
        $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
    },
    'records older Standard Security no-copy permission policies in pandoc metadata' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent, $standardR3EncryptedPdfWithContent, $standardR4V2EncryptedPdfWithContent, $standardR4AesV2EncryptedPdfWithContent, $standardR5AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Older no copy Text) Tj T* (Older permission line) Tj ET';
        $cases = [
            'Standard-R2-empty-user-password' => $standardR2EncryptedPdfWithContent($content, -20),
            'Standard-R3-empty-user-password-RC4' => $standardR3EncryptedPdfWithContent($content, -20),
            'Standard-R4-empty-user-password-RC4' => $standardR4V2EncryptedPdfWithContent($content, 'StdCF', 'StdCF', -20),
            'Standard-R4-empty-user-password-AESV2' => $standardR4AesV2EncryptedPdfWithContent($content, 'StdCF', 'StdCF', '', '', -20),
            'Standard-R5-empty-user-password-AESV3' => $standardR5AesV3EncryptedPdfWithContent($content, 'StdCF', 'StdCF', -20),
        ];

        foreach ($cases as $expectedHandler => $pdf) {
            $document = (new PdfReader())->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $meta = $document->attr('meta');
            $warningText = implode("\n", $meta['pdfWarnings']);

            $t->contains('Older no copy Text', $blocks);
            $t->contains('Older permission line', $blocks);
            $t->same(true, $meta['pdfEncrypted']);
            $t->same(true, $meta['pdfEncryptionDecrypted']);
            $t->same($expectedHandler, $meta['pdfEncryptionHandler']);
            $t->same('empty-user-password', $meta['pdfEncryptionPasswordType']);
            $t->same(false, $meta['pdfEncryptionPermissions']['copy']);
            $t->same(4294967276, $meta['pdfEncryptionPermissions']['unsigned']);
            $t->same(false, $meta['pdfEncryptionAllowsContentExtraction']);
            $t->same(false, $meta['pdfDiagnostics']['encryptionPermissions']['copy']);
            $t->same(false, $meta['pdfDiagnostics']['encryptionAllowsContentExtraction']);
            $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
        }
    },
    'records non-copy Standard permission restrictions without extraction warning in pandoc metadata' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent, $standardR3EncryptedPdfWithContent, $standardR4V2EncryptedPdfWithContent, $standardR4AesV2EncryptedPdfWithContent, $standardR5AesV3EncryptedPdfWithContent, $standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Restricted but copyable Text) Tj T* (Policy detail line) Tj ET';
        $permissions = -3888;
        $cases = [
            'Standard-R2-empty-user-password' => $standardR2EncryptedPdfWithContent($content, $permissions),
            'Standard-R3-empty-user-password-RC4' => $standardR3EncryptedPdfWithContent($content, $permissions),
            'Standard-R4-empty-user-password-RC4' => $standardR4V2EncryptedPdfWithContent($content, 'StdCF', 'StdCF', $permissions),
            'Standard-R4-empty-user-password-AESV2' => $standardR4AesV2EncryptedPdfWithContent($content, 'StdCF', 'StdCF', '', '', $permissions),
            'Standard-R5-empty-user-password-AESV3' => $standardR5AesV3EncryptedPdfWithContent($content, 'StdCF', 'StdCF', $permissions),
            'Standard-R6-empty-user-password-AESV3' => $standardR6AesV3EncryptedPdfWithContent($content, 'StdCF', 'StdCF', '', '', false, false, $permissions),
        ];

        foreach ($cases as $expectedHandler => $pdf) {
            $document = (new PdfReader())->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $meta = $document->attr('meta');
            $warningText = implode("\n", $meta['pdfWarnings']);

            $t->contains('Restricted but copyable Text', $blocks);
            $t->contains('Policy detail line', $blocks);
            $t->same(true, $meta['pdfEncrypted']);
            $t->same(true, $meta['pdfEncryptionDecrypted']);
            $t->same($expectedHandler, $meta['pdfEncryptionHandler']);
            $t->same('empty-user-password', $meta['pdfEncryptionPasswordType']);
            $t->same($permissions, $meta['pdfEncryptionPermissions']['raw']);
            $t->same(4294963408, $meta['pdfEncryptionPermissions']['unsigned']);
            $t->same(false, $meta['pdfEncryptionPermissions']['print']);
            $t->same(false, $meta['pdfEncryptionPermissions']['modify']);
            $t->same(true, $meta['pdfEncryptionPermissions']['copy']);
            $t->same(false, $meta['pdfEncryptionPermissions']['annotate']);
            $t->same(false, $meta['pdfEncryptionPermissions']['fillForms']);
            $t->same(false, $meta['pdfEncryptionPermissions']['extractAccessibility']);
            $t->same(false, $meta['pdfEncryptionPermissions']['assemble']);
            $t->same(false, $meta['pdfEncryptionPermissions']['printHighResolution']);
            $t->same(true, $meta['pdfEncryptionAllowsContentExtraction']);
            $t->same(true, $meta['pdfDiagnostics']['encryptionAllowsContentExtraction']);
            $t->true(!str_contains($warningText, 'PDF permissions disallow content copying or extraction.'));
        }
    },
    'records unsigned Standard permission literals in pandoc metadata' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Unsigned permission Text) Tj T* (Unsigned policy line) Tj ET';
        $permissions = 4294967276;
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, 'StdCF', 'StdCF', '', '', false, false, $permissions);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Unsigned permission Text', $blocks);
        $t->contains('Unsigned policy line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same('empty-user-password', $meta['pdfEncryptionPasswordType']);
        $t->same($permissions, $meta['pdfEncryptionPermissions']['raw']);
        $t->same($permissions, $meta['pdfEncryptionPermissions']['unsigned']);
        $t->same(false, $meta['pdfEncryptionPermissions']['copy']);
        $t->same(false, $meta['pdfEncryptionAllowsContentExtraction']);
        $t->same(false, $meta['pdfDiagnostics']['encryptionAllowsContentExtraction']);
        $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
    },
    'reads Standard R6 AESV3 encrypted pdf with supplied user password into pandoc ast blocks' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 AES user PDF Text) Tj T* (Second R6 AES user line) Tj ET', 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $lockedDocument = (new PdfReader())->read($pdf);
        $lockedMeta = $lockedDocument->attr('meta');
        $document = (new PdfReader(['password' => 'reader-pass']))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->same(true, $lockedMeta['pdfEncrypted']);
        $t->same(false, $lockedMeta['pdfEncryptionDecrypted']);
        $t->contains('Encrypted R6 AES user PDF Text', $blocks);
        $t->contains('Second R6 AES user line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R6-user-password-AESV3', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reads Standard R6 AESV3 encrypted pdf with supplied owner password into pandoc ast blocks' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 AES owner PDF Text) Tj T* (Second R6 AES owner line) Tj ET', 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $document = (new PdfReader(['password' => 'owner-pass']))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 AES owner PDF Text', $blocks);
        $t->contains('Second R6 AES owner line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-owner-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionDecrypted']);
        $t->same('Standard-R6-owner-password-AESV3', $meta['pdfDiagnostics']['encryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'records Standard R4 AESV2 owner-password permission override in pandoc metadata' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $pdf = $standardR4AesV2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 owner permission Text) Tj T* (Older owner override line) Tj ET', 'StdCF', 'StdCF', 'reader-pass', 'owner-pass', -20);
        $document = (new PdfReader(['password' => 'owner-pass']))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 owner permission Text', $blocks);
        $t->contains('Older owner override line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-owner-password-AESV2', $meta['pdfEncryptionHandler']);
        $t->same('owner-password', $meta['pdfEncryptionPasswordType']);
        $t->same(false, $meta['pdfEncryptionPermissions']['copy']);
        $t->same(true, $meta['pdfEncryptionAllowsContentExtraction']);
        $t->same(false, $meta['pdfDiagnostics']['encryptionPermissions']['copy']);
        $t->same(true, $meta['pdfDiagnostics']['encryptionAllowsContentExtraction']);
        $t->true(!str_contains($warningText, 'PDF permissions disallow content copying or extraction.'));
    },
    'reads Standard R4 AESV2 encrypted object streams into pandoc ast blocks without false warnings' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $pdf = $standardR4AesV2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 object stream PDF Text) Tj T* (R4 packed page resource line) Tj ET', 'StdCF', 'StdCF', '', '', -4, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 object stream PDF Text', $blocks);
        $t->contains('R4 packed page resource line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'reads Standard R4 AESV2 encrypted xref stream object entries into pandoc ast blocks' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $pdf = $standardR4AesV2EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R4 xref object stream PDF Text) Tj T* (Selected R4 encrypted packed page line) Tj ET', 'StdCF', 'StdCF', '', '', -4, true, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R4 xref object stream PDF Text', $blocks);
        $t->contains('Selected R4 encrypted packed page line', $blocks);
        $t->true(!str_contains($blocks, 'Ignored R4 encrypted xref duplicate'));
        $t->true(!str_contains($blocks, 'Ignored R4 encrypted xref object stream stale'));
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(0, $meta['pdfMalformedXrefStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'xref stream'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'reads Standard R5 AESV3 encrypted object streams into pandoc ast blocks without false warnings' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR5AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R5 object stream PDF Text) Tj T* (R5 packed page resource line) Tj ET', 'StdCF', 'StdCF', -4, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R5 object stream PDF Text', $blocks);
        $t->contains('R5 packed page resource line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'reads Standard R5 AESV3 encrypted xref stream object entries into pandoc ast blocks' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR5AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R5 xref object stream PDF Text) Tj T* (Selected R5 encrypted packed page line) Tj ET', 'StdCF', 'StdCF', -4, true, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R5 xref object stream PDF Text', $blocks);
        $t->contains('Selected R5 encrypted packed page line', $blocks);
        $t->true(!str_contains($blocks, 'Ignored R5 encrypted xref duplicate'));
        $t->true(!str_contains($blocks, 'Ignored R5 encrypted xref object stream stale'));
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(0, $meta['pdfMalformedXrefStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'xref stream'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'reads Standard R6 AESV3 encrypted object streams into pandoc ast blocks without false warnings' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 object stream PDF Text) Tj T* (Packed page resource line) Tj ET', 'StdCF', 'StdCF', '', '', true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 object stream PDF Text', $blocks);
        $t->contains('Packed page resource line', $blocks);
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'reads Standard R6 AESV3 encrypted xref streams into pandoc ast blocks without stale duplicates' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 xref stream PDF Text) Tj T* (Selected encrypted xref line) Tj ET', 'StdCF', 'StdCF', '', '', false, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 xref stream PDF Text', $blocks);
        $t->contains('Selected encrypted xref line', $blocks);
        $t->true(!str_contains($blocks, 'Ignored encrypted xref duplicate'));
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same([], $meta['pdfDiagnostics']['malformedXrefOffsets']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'Malformed PDF xref'));
    },
    'reads Standard R6 AESV3 encrypted xref stream object entries into pandoc ast blocks' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $pdf = $standardR6AesV3EncryptedPdfWithContent('BT /F1 12 Tf 72 720 Td (Encrypted R6 xref object stream PDF Text) Tj T* (Selected encrypted packed page line) Tj ET', 'StdCF', 'StdCF', '', '', true, true);
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->contains('Encrypted R6 xref object stream PDF Text', $blocks);
        $t->contains('Selected encrypted packed page line', $blocks);
        $t->true(!str_contains($blocks, 'Ignored encrypted xref duplicate'));
        $t->true(!str_contains($blocks, 'Ignored encrypted xref object stream stale'));
        $t->same(true, $meta['pdfEncrypted']);
        $t->same(true, $meta['pdfEncryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $meta['pdfEncryptionHandler']);
        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same([], $meta['pdfDiagnostics']['malformedXrefOffsets']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'Malformed PDF xref'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'records pdf page-level partial extraction diagnostics in pandoc metadata' => static function (TestRunner $t) use ($pdfWithPartialPageExtractionIssues): void {
        $document = (new PdfReader())->read($pdfWithPartialPageExtractionIssues());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->same(1, $meta['pdfTextLines']);
        $t->same(2, $meta['pdfPagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 2,
                'pageObject' => 6,
                'contentReference' => 7,
                'contentObject' => 7,
                'reason' => 'unsupported_content_filter',
                'filters' => ['DCTDecode'],
            ],
            [
                'page' => 3,
                'pageObject' => 8,
                'contentReference' => 99,
                'contentObject' => null,
                'reason' => 'unresolved_content_reference',
                'filters' => [],
            ],
        ], $meta['pdfPageExtractionIssues']);
        $t->contains('Readable Page Text', $blocks);
        $t->true(!str_contains($blocks, 'Unsupported Page Text'));
        $t->contains('PDF page-level extraction issues: 2 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'records pdf Form XObject page-level partial extraction diagnostics in pandoc metadata' => static function (TestRunner $t) use ($pdfWithFormXObjectPageExtractionIssues): void {
        $document = (new PdfReader())->read($pdfWithFormXObjectPageExtractionIssues());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->same(1, $meta['pdfTextLines']);
        $t->same(['DCTDecode'], $meta['pdfUnsupportedFilters']);
        $t->same(1, $meta['pdfFailedStreams']);
        $t->same(1, $meta['pdfPagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmBad',
                'xObjectObject' => 6,
                'xObjectSubtype' => 'Form',
                'reason' => 'unsupported_form_xobject_filter',
                'filters' => ['DCTDecode'],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmBroken',
                'xObjectObject' => 7,
                'xObjectSubtype' => 'Form',
                'reason' => 'failed_form_xobject_decode',
                'filters' => ['FlateDecode'],
            ],
        ], $meta['pdfPageExtractionIssues']);
        $t->contains('Visible Page Text', $blocks);
        $t->true(!str_contains($blocks, 'Unsupported Form Text'));
        $t->contains('PDF page-level extraction issues: 1 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'records pdf unresolved XObject page-level partial extraction diagnostics in pandoc metadata' => static function (TestRunner $t) use ($pdfWithUnresolvedXObjectPageExtractionIssues): void {
        $document = (new PdfReader())->read($pdfWithUnresolvedXObjectPageExtractionIssues());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $warningText = implode("\n", $meta['pdfWarnings']);

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfUnsupportedFilters']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->same(1, $meta['pdfPagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmMissing',
                'xObjectObject' => 99,
                'xObjectSubtype' => 'Unknown',
                'reason' => 'unresolved_xobject_reference',
                'filters' => [],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmAbsent',
                'xObjectObject' => null,
                'xObjectSubtype' => 'Unknown',
                'reason' => 'unresolved_xobject_resource',
                'filters' => [],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmMalformed',
                'xObjectObject' => 6,
                'xObjectSubtype' => 'Form',
                'reason' => 'unresolved_form_xobject_stream',
                'filters' => [],
            ],
        ], $meta['pdfPageExtractionIssues']);
        $t->contains('Visible XObject Resource Text', $blocks);
        $t->contains('PDF page-level extraction issues: 1 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'converts searchable pdf bytes to wordpress blocks through converter' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 24 Tf 72 720 Td (PDF Converter Title) Tj T* '
            . '(See https://example.test/demo.) Tj T* '
            . '(1. Ordered item) Tj ET'
        );

        $blocks = PandocConverter::convert($pdf, 'pdf', 'blocks');

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('PDF Converter Title', $blocks);
        $t->contains('<a href="https://example.test/demo">https://example.test/demo</a>.', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->contains('<li>Ordered item</li>', $blocks);
    },
    'maps aligned searchable pdf text rows into a table block' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf 72 720 Td (Name    Amount) Tj T* '
            . '(Alpha   10) Tj T* '
            . '(Beta    20) Tj ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same(1, $meta['pdfDetectedTables']);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th>Name</th><th>Amount</th>', $blocks);
        $t->contains('<td>Alpha</td><td>10</td>', $blocks);
        $t->contains('<td>Beta</td><td>20</td>', $blocks);
    },
    'maps positioned untagged pdf grid rows into a table block' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 250 720 Tm (Qty) Tj 1 0 0 1 320 720 Tm (Size) Tj '
            . '1 0 0 1 72 704 Tm (Widget Alpha) Tj 1 0 0 1 250 704 Tm (30) Tj 1 0 0 1 320 704 Tm (40x60x80mm) Tj '
            . '1 0 0 1 72 688 Tm (stackable storage case) Tj '
            . '1 0 0 1 72 672 Tm (Organizer) Tj 1 0 0 1 250 672 Tm (12) Tj 1 0 0 1 320 672 Tm (120x200mm) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same(1, $meta['pdfGeometryTables']);
        $t->same('geometry', $meta['pdfTableReconstruction']);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th>Product</th><th>Qty</th><th>Size</th>', $blocks);
        $t->contains('<td>Widget Alpha stackable storage case</td><td>30</td><td>40x60x80mm</td>', $blocks);
        $t->contains('<td>Organizer</td><td>12</td><td>120x200mm</td>', $blocks);
    },
    'can bypass positioned pdf geometry tables for bounded text extraction' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 250 720 Tm (Qty) Tj '
            . '1 0 0 1 72 704 Tm (Widget Alpha) Tj 1 0 0 1 250 704 Tm (30) Tj '
            . 'ET'
        );

        $document = (new PdfReader(['pdfGeometryTables' => false]))->read($pdf);
        $meta = $document->attr('meta');

        $t->same(false, $meta['pdfGeometryTablesEnabled']);
        $t->same(0, $meta['pdfGeometryTables']);
        $t->same('text', $meta['pdfTableReconstruction']);
        $t->same(0, $meta['pdfPositionedTextRuns']);
        $t->true(in_array($document->children[0]->type, ['heading', 'paragraph'], true));
        $t->contains('Product', $document->children[0]->attr('text'));
        $t->contains('Widget Alpha', $document->children[1]->attr('text'));
    },
    'repairs glued prose in bounded pdf text extraction' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (TechnicaldocumentscanlosewordspacesduringPDFextraction.) Tj '
            . '1 0 0 1 72 732 Tm (Thisreaderusesadictionarybasedrepairtopreserveprose.) Tj '
            . '1 0 0 1 72 716 Tm (Adjacentlinescanformavisualparagraphwithoutspecialcases.) Tj '
            . '1 0 0 1 72 700 Tm (Markdownexamplesexerciseformatflavorfeatures.) Tj '
            . '1 0 0 1 72 684 Tm (Section3describesgenericlayoutrepair.Figure2showscolumns.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
        $meta = $document->attr('meta');

        $t->same(true, $meta['pdfTextRepair']);
        $t->contains('Technical documents can lose word spaces during PDF extraction.', $text);
        $t->contains('This reader uses a dictionary based repair to preserve prose.', $text);
        $t->contains('Adjacent lines can form a visual paragraph without special cases.', $text);
        $t->contains('Markdown examples exercise format flavor features.', $text);
        $t->contains('Section 3 describes generic layout repair. Figure 2 shows columns.', $text);
        $t->true(!str_contains($html, '<h2>Technical documents'), 'wrapped prose line is not promoted to a heading');
        $t->true(!str_contains($text, 'documentscanlosewordspaces'), 'glued prose is segmented');
    },
    'removes standalone pdf brace artifacts during prose repair' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (TechnicaldocumentscanlosewordspacesduringPDFextraction.) Tj '
            . '1 0 0 1 72 732 Tm (Pro viders should practice hand hygiene: { } Every time they enter your room.) Tj '
            . '1 0 0 1 72 716 Tm (Before prep a ring food, keep ha nds clean by as king people to wash.) Tj '
            . '1 0 0 1 72 700 Tm (Thisreaderusesadictionarybasedrepairtopreserveprose.) Tj '
            . '1 0 0 1 72 684 Tm (Adjacentlinescanformavisualparagraphwithoutspecialcases.) Tj '
            . '1 0 0 1 72 668 Tm (Markdownexamplesexerciseformatflavorfeatures.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(PandocConverter::write($document, 'html')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
        $meta = $document->attr('meta');

        $t->same(true, $meta['pdfTextRepair']);
        $t->contains('Providers should practice hand hygiene: Every time they enter your room.', $text);
        $t->contains('Before preparing food, keep hands clean by asking people to wash.', $text);
        $t->true(!str_contains($text, '{ }'));
        $t->true(!str_contains($text, 'hygiene: { } Every'));
        $t->true(!str_contains($text, 'Pro viders'));
        $t->true(!str_contains($text, 'prep a ring'));
        $t->true(!str_contains($text, 'ha nds'));
        $t->true(!str_contains($text, 'as king'));
    },
    'turns repeated embedded pdf bullet markers into a list' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (Hand hygiene: \225 Wash hands with soap \225 Dry hands with a towel) Tj '
            . 'ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj\n"
            . "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>endobj\n"
            . "5 0 obj<</Length " . strlen($content) . ">>stream\n{$content}\nendstream\nendobj\n"
            . "trailer<</Root 1 0 R>>\n%%EOF";

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('bullet_list', $document->children[1]->type);
        $t->same(2, count($document->children[1]->children));
        $t->contains('<ul>', $html);
        $t->contains('<li><p>Wash hands with soap</p></li>', $html);
        $t->contains('<li><p>Dry hands with a towel</p></li>', $html);
    },
    'turns repeated sequential embedded pdf ordered markers into a list' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (With soap and water: 1. Wet your hands with warm water. 2. Rub your hands together. 3. Rinse your hands well.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('ordered_list', $document->children[1]->type);
        $t->same(3, count($document->children[1]->children));
        $t->contains('<ol>', $html);
        $t->contains('<li><p>Wet your hands with warm water.</p></li>', $html);
        $t->contains('<li><p>Rub your hands together.</p></li>', $html);
        $t->contains('<li><p>Rinse your hands well.</p></li>', $html);
    },
    'keeps explicit pdf list markers as repaired prose block boundaries' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (intro without punctuation marker) Tj '
            . '1 0 0 1 72 732 Tm (- First explicit item.) Tj '
            . '1 0 0 1 72 716 Tm (- Second explicit item.) Tj '
            . '1 0 0 1 72 700 Tm (Trailing paragraph continues normally.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same('heading', $document->children[0]->type);
        $t->same('bullet_list', $document->children[1]->type);
        $t->same('paragraph', $document->children[2]->type);
        $t->contains('intro without punctuation marker', $html);
        $t->contains('<li><p>First explicit item.</p></li>', $html);
        $t->contains('<li><p>Second explicit item.</p></li>', $html);
        $t->contains('<p>Trailing paragraph continues normally.</p>', $html);
    },
    'keeps embedded pdf list runs as repaired prose block boundaries' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (intro without punctuation marker) Tj '
            . '1 0 0 1 72 732 Tm (Steps: 1. First step. 2. Second step.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same('heading', $document->children[0]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('ordered_list', $document->children[2]->type);
        $t->contains('intro without punctuation marker', $html);
        $t->contains('<p>Steps:</p>', $html);
        $t->contains('<li><p>First step.</p></li>', $html);
        $t->contains('<li><p>Second step.</p></li>', $html);
    },
    'keeps non-list pdf prose with isolated markers as paragraphs' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (Room 2. Rub rails are cleaned daily, and one \225 marker alone is not a list.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->true(!str_contains($html, '<ul>'));
        $t->true(!str_contains($html, '<ol>'));
    },
    'preserves visual TJ array word gaps before prose repair' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm [(Health) -260 (care) -260 (providers) -260 (should) -260 (practice) -260 (hand) -260 (hygiene)] TJ '
            . '1 0 0 1 72 732 Tm [(Before) -260 (putting) -260 (on) -260 (gloves.)] TJ '
            . '1 0 0 1 72 716 Tm [(Hand) -260 (hygiene) -260 (saves) -260 (lives.)] TJ '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(PandocConverter::write($document, 'html')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';

        $t->contains('Health care providers should practice hand hygiene', $text);
        $t->contains('Before putting on gloves.', $text);
        $t->contains('Hand hygiene saves lives.', $text);
        $t->true(!str_contains($text, 'Healthcareproviders'));
        $t->true(!str_contains($text, 'Beforeputtingongloves'));
        $t->true(!str_contains($text, 'Handhygienesaveslives'));
    },
    'keeps raw pdf text when positioned fragments would introduce glued words' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (Befor) Tj '
            . '1 0 0 1 103 748 Tm (e ) Tj '
            . '1 0 0 1 101 748 Tm (put) Tj '
            . '1 0 0 1 119 748 Tm (t) Tj '
            . '1 0 0 1 125 748 Tm (ing ) Tj '
            . '1 0 0 1 147 748 Tm (on ) Tj '
            . '1 0 0 1 166 748 Tm (gloves.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(PandocConverter::write($document, 'html')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';

        $t->contains('Before putting on gloves.', $text);
        $t->true(!str_contains($text, 'Beforputetingongloves'));
    },
    'orders positioned prose columns before repairing pdf text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 748 Tm (Leftcolumnfirstsentencecontinues) Tj '
            . '1 0 0 1 330 748 Tm (Rightcolumnshouldnotinterleave) Tj '
            . '1 0 0 1 72 732 Tm (beforethesecondcolumnstarts.) Tj '
            . '1 0 0 1 330 732 Tm (withleftcolumnrows.) Tj '
            . '1 0 0 1 72 716 Tm (Leftcolumnthirdlinefinishes.) Tj '
            . '1 0 0 1 330 716 Tm (Rightcolumnthirdlinefinishes.) Tj '
            . 'ET'
        );

        $document = (new PdfReader([
            'pdfGeometryTables' => false,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(PandocConverter::write($document, 'html')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
        $meta = $document->attr('meta');

        $t->same('positioned', $meta['pdfTextRepairSource']);
        $leftFirst = strpos($text, 'Left column first sentence continues');
        $leftThird = strpos($text, 'Left column third line finishes.');
        $rightFirst = strpos($text, 'Right column should not interleave');
        $t->true($leftFirst !== false, 'left column prose is segmented');
        $t->true($leftThird !== false, 'left column remains contiguous');
        $t->true($rightFirst !== false, 'right column prose is segmented');
        $t->true($leftThird < $rightFirst, 'left column is emitted before right column');
    },
    'preserves positioned pdf tables embedded between surrounding text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 752 Tm (Project summary for case CASE-104) Tj '
            . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 250 720 Tm (Qty) Tj 1 0 0 1 320 720 Tm (Size) Tj '
            . '1 0 0 1 72 704 Tm (Widget Alpha) Tj 1 0 0 1 250 704 Tm (30) Tj 1 0 0 1 320 704 Tm (40x60x80mm) Tj '
            . '1 0 0 1 72 688 Tm (stackable storage case) Tj '
            . '1 0 0 1 72 672 Tm (Organizer) Tj 1 0 0 1 250 672 Tm (12) Tj 1 0 0 1 320 672 Tm (120x200mm) Tj '
            . '1 0 0 1 72 640 Tm (Payment due in 30 days) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Project summary for case CASE-104', $document->children[0]->attr('text'));
        $t->same('table', $document->children[1]->type);
        $t->same('paragraph', $document->children[2]->type);
        $t->same('Payment due in 30 days', $document->children[2]->attr('text'));
        $t->same(1, $meta['pdfDetectedTables']);
        $t->same(1, $meta['pdfGeometryTables']);
        $t->same('geometry', $meta['pdfTableReconstruction']);
        $t->contains('Project summary for case CASE-104', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th>Product</th><th>Qty</th><th>Size</th>', $blocks);
        $t->contains('<td>Widget Alpha stackable storage case</td><td>30</td><td>40x60x80mm</td>', $blocks);
        $t->contains('Payment due in 30 days', $blocks);
    },
    'does not promote positioned prose word fragments to a table' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (healthcare) Tj 1 0 0 1 160 720 Tm (providers) Tj 1 0 0 1 250 720 Tm (should) Tj 1 0 0 1 340 720 Tm (basedhandrub.) Tj '
            . '1 0 0 1 72 704 Tm (practice) Tj 1 0 0 1 160 704 Tm (hand) Tj 1 0 0 1 250 704 Tm (hygiene.) Tj '
            . '1 0 0 1 250 688 Tm (•) Tj 1 0 0 1 340 688 Tm (Preventingthespread ofgermsandinfections.) Tj '
            . 'ET'
        );

        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfDetectedTables']);
        $t->same(0, $meta['pdfGeometryTables']);
        $t->same('text', $meta['pdfTableReconstruction']);
        $t->true(!str_contains($blocks, '<!-- wp:table -->'));
        $t->contains('healthcare', $blocks);
        $t->contains('based hand rub.', $blocks);
        $t->contains('Preventing the spread of germs and infections.', $blocks);
    },
    'keeps positioned numeric grids as tables after fragment grid filtering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 180 720 Tm (Qty) Tj 1 0 0 1 260 720 Tm (Rate) Tj 1 0 0 1 340 720 Tm (Total) Tj '
            . '1 0 0 1 72 704 Tm (Alpha) Tj 1 0 0 1 180 704 Tm (2) Tj 1 0 0 1 260 704 Tm ($3.00) Tj 1 0 0 1 340 704 Tm ($6.00) Tj '
            . '1 0 0 1 72 688 Tm (Beta) Tj 1 0 0 1 180 688 Tm (1) Tj 1 0 0 1 260 688 Tm ($4.00) Tj 1 0 0 1 340 688 Tm ($4.00) Tj '
            . 'ET'
        );

        $document = (new PdfReader(['pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfDetectedTables']);
        $t->same(1, $meta['pdfGeometryTables']);
        $t->same('geometry', $meta['pdfTableReconstruction']);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<th>Product</th><th>Qty</th><th>Rate</th><th>Total</th>', $blocks);
        $t->contains('<td>Alpha</td><td>2</td><td>$3.00</td><td>$6.00</td>', $blocks);
    },
    'splits positioned pdf tables at section breaks instead of one sparse table' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 760 Tm (People) Tj '
            . '1 0 0 1 72 744 Tm (Seller) Tj 1 0 0 1 300 744 Tm (Buyer) Tj '
            . '1 0 0 1 72 728 Tm (Name A) Tj 1 0 0 1 300 728 Tm (Name B) Tj '
            . '1 0 0 1 72 680 Tm (Items) Tj '
            . '1 0 0 1 72 660 Tm (Invoice currency USD) Tj '
            . '1 0 0 1 72 632 Tm (SKU) Tj 1 0 0 1 160 632 Tm (Description) Tj 1 0 0 1 330 632 Tm (Qty) Tj 1 0 0 1 420 632 Tm (Total) Tj '
            . '1 0 0 1 72 616 Tm (A1) Tj 1 0 0 1 160 616 Tm (Folder pack) Tj 1 0 0 1 330 616 Tm (3) Tj 1 0 0 1 420 616 Tm (12.00) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, substr_count($blocks, '<!-- wp:table -->'));
        $t->same(2, $meta['pdfDetectedTables']);
        $t->same(2, $meta['pdfGeometryTables']);
        $t->contains('<th>Seller</th><th>Buyer</th>', $blocks);
        $t->contains('<td>Name A</td><td>Name B</td>', $blocks);
        $t->contains('<th>SKU</th><th>Description</th><th>Qty</th><th>Total</th>', $blocks);
        $t->contains('<td>A1</td><td>Folder pack</td><td>3</td><td>12.00</td>', $blocks);
        $t->contains('Items', $blocks);
        $t->contains('Invoice currency USD', $blocks);
        $t->true(!str_contains($blocks, '<th>Seller</th><th>Buyer</th><th></th>'));
    },
    'preserves word gaps inside positioned pdf table cells' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'BT /F1 10 Tf '
            . '1 0 0 1 72 720 Tm (Details) Tj 1 0 0 1 330 720 Tm (Code) Tj '
            . '1 0 0 1 72 704 Tm (Client ) Tj 1 0 0 1 101 704 Tm (number: ) Tj 1 0 0 1 145 704 Tm (CASE-104) Tj 1 0 0 1 330 704 Tm (USD) Tj '
            . '1 0 0 1 72 688 Tm (Issue ) Tj 1 0 0 1 96 688 Tm (date, ) Tj 1 0 0 1 128 688 Tm (with ) Tj 1 0 0 1 156 688 Tm (reference) Tj 1 0 0 1 330 688 Tm (11.05.2026) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same('geometry', $meta['pdfTableReconstruction']);
        $t->contains('<td>Client number: CASE-104</td><td>USD</td>', $blocks);
        $t->contains('<td>Issue date, with reference</td><td>11.05.2026</td>', $blocks);
        $t->true(!str_contains($blocks, 'Clientnumber'));
        $t->true(!str_contains($blocks, 'Issuedate'));
    },
    'preserves decoded word gaps and rgb table cell fills' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'q /DeviceRGB cs 0.9647058823529412 0.9686274509803922 0.9803921568627451 scn 70 714 320 18 re f 420 714 85 18 re f Q '
            . 'BT /F1 10 Tf '
            . '1 0 0 1 72 720 Tm (Details) Tj 1 0 0 1 430 720 Tm (Code) Tj '
            . '1 0 0 1 72 704 Tm (Payment ) Tj 1 0 0 1 112 704 Tm (terms, ) Tj 1 0 0 1 144 704 Tm (with ) Tj '
            . '1 0 0 1 172 704 Tm (reference ) Tj 1 0 0 1 232 704 Tm (section. ) Tj 1 0 0 1 283 704 Tm (1 ) Tj 1 0 0 1 292 704 Tm (total:) Tj 1 0 0 1 430 704 Tm (11.05.2026) Tj '
            . '1 0 0 1 72 688 Tm (Currency ) Tj 1 0 0 1 112 688 Tm (code:) Tj 1 0 0 1 430 688 Tm (USD) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same(1, $meta['pdfGeometryTables']);
        $t->same(2, $meta['pdfFilledRectangles']);
        $t->contains('<th data-pdf-fill-color="#f6f7fa" style="background-color:#f6f7fa">Details</th><th data-pdf-fill-color="#f6f7fa" style="background-color:#f6f7fa">Code</th>', $blocks);
        $t->contains('<td>Payment terms, with reference section. 1 total:</td><td>11.05.2026</td>', $blocks);
        $t->contains('<td>Currency code:</td><td>USD</td>', $blocks);
        $t->true(!str_contains($blocks, 'Paymentterms'));
        $t->true(!str_contains($blocks, 'referencesection'));
        $t->true(!str_contains($blocks, 'Currencycode'));
    },
    'does not hardcode local problematic pdf invoice strings in extraction sources' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $sources = [
            $root . '/lanes/markerpdf/src/PdfTextExtractor.php',
            $root . '/lanes/pandoc/src/PdfReader.php',
            $root . '/lanes/pandoc/src/WordPressBlockWriter.php',
        ];
        $forbidden = [
            '/home/claude/' . 'invoice.pdf',
            'Data' . 'wystawienia',
            'z' . 'zastrze' . "\u{017C}" . 'eniem',
            'Kartoteka' . ' sk' . "\u{0142}" . 'adana',
        ];

        foreach ($sources as $sourcePath) {
            $source = file_get_contents($sourcePath);
            $t->true(is_string($source));
            foreach ($forbidden as $needle) {
                $t->true(!str_contains((string) $source, $needle), basename($sourcePath) . ' must not special-case local invoice content.');
            }
        }
    },
    'preserves background fills on empty positioned pdf cells' => static function (TestRunner $t) use ($pdfWithContent): void {
        $pdf = $pdfWithContent(
            'q 0.9 g 112 698 80 18 re f Q '
            . 'BT /F1 10 Tf '
            . '1 0 0 1 72 720 Tm (Left) Tj 1 0 0 1 152 720 Tm (Middle) Tj 1 0 0 1 232 720 Tm (Right) Tj '
            . '1 0 0 1 72 704 Tm (A) Tj 1 0 0 1 232 704 Tm (C) Tj '
            . 'ET'
        );

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('table', $document->children[0]->type);
        $t->same(1, $meta['pdfGeometryTables']);
        $t->same(1, $meta['pdfFilledRectangles']);
        $t->contains('<th>Left</th><th>Middle</th><th>Right</th>', $blocks);
        $t->contains('<td>A</td><td data-pdf-fill-color="#e6e6e6" style="background-color:#e6e6e6"></td><td>C</td>', $blocks);
    },
    'uses pdf simple font Widths for positioned text in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithSimpleFontWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithSimpleFontWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf CID W widths for positioned text in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf non-Identity CID CMap source mappings for positioned text widths' => static function (TestRunner $t) use ($pdfWithNonIdentityCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithNonIdentityCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf explicit positive CMap CID operands for positioned text widths' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidOperandsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<0101> <0057>\n"
            . "<0102> <0069>\n"
            . "<0103> <0064>\n"
            . "<0104> <0065>\n"
            . "<0105> <0054>\n"
            . "<0106> <0061>\n"
            . "<0107> <0069>\n"
            . "<0108> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidOperandsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "<0101> <0108> +1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "<0101> +1\n"
            . "<0102> +2\n"
            . "<0103> +3\n"
            . "<0104> +4\n"
            . "<0105> +5\n"
            . "<0106> +6\n"
            . "<0107> +7\n"
            . "<0108> +8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <0101010201030104> Tj 1 0 0 1 108 720 Tm <0105010601070108> Tj ET '
            . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<0101010201030104>] TJ 1 0 0 1 108 704 Tm <0105010601070108> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PositiveCidRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PositiveCidWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PositiveCidCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf CID CMap literal source mappings for positioned text widths' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<41> <48>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<41> <0057>\n"
            . "<42> <0069>\n"
            . "<43> <0064>\n"
            . "<44> <0065>\n"
            . "<45> <0054>\n"
            . "<46> <0061>\n"
            . "<47> <0069>\n"
            . "<48> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "(A) (H)\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "(A) (H) 1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "(A) (H)\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "(A) 1\n"
            . "(B) 2\n"
            . "(C) 3\n"
            . "(D) 4\n"
            . "(E) 5\n"
            . "(F) 6\n"
            . "(G) 7\n"
            . "(H) 8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj 1 0 0 1 108 720 Tm <45464748> Tj ET '
            . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<41424344>] TJ 1 0 0 1 108 704 Tm <45464748> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralSourceWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf CID CMap notdef mappings for positioned text widths' => static function (TestRunner $t) use ($pdfWithCidNotdefWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithCidNotdefWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('EdgeCase', $blocks);
        $t->true(!str_contains($blocks, 'Edge Case'));
        $t->same('EdgeCase', $document->children[0]->children[0]->attr('text'));
        $t->same('EdgeCase', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf named CID CMap resources for positioned text widths' => static function (TestRunner $t) use ($pdfWithNamedCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithNamedCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf bundled predefined CID CMaps for positioned text widths' => static function (TestRunner $t) use ($pdfWithPredefinedCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithPredefinedCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf bundled predefined CNS CID CMaps for positioned text widths' => static function (TestRunner $t) use ($pdfWithPredefinedCnsCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithPredefinedCnsCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf bundled predefined CID UseCMap inheritance for positioned text widths' => static function (TestRunner $t) use ($pdfWithPredefinedInheritedCnsCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithPredefinedInheritedCnsCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf complete bundled predefined CID CMap collection for positioned text widths' => static function (TestRunner $t) use ($pdfWithPredefinedJapanCidWidthsForPositioning): void {
        $document = (new PdfReader())->read($pdfWithPredefinedJapanCidWidthsForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf predefined Unicode source CID CMaps without ToUnicode in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithPredefinedUnicodeSourceCidMapWithoutToUnicode): void {
        $document = (new PdfReader())->read($pdfWithPredefinedUnicodeSourceCidMapWithoutToUnicode());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->contains('WideTail', $blocks);
        $t->true(!str_contains($blocks, 'Wide Tail'));
        $t->true(!str_contains($blocks, '8JEF'));
        $t->same('WideTail', $document->children[0]->children[0]->attr('text'));
        $t->same('WideTail', $document->children[1]->children[0]->attr('text'));
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf predefined UTF8 source CMap fallback bytes in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithPredefinedUtf8SourceFallbackWithoutToUnicode): void {
        $document = (new PdfReader())->read($pdfWithPredefinedUtf8SourceFallbackWithoutToUnicode());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $expected = html_entity_decode('&#x1F600;', ENT_QUOTES, 'UTF-8') . 'Wide';

        $t->same(1, $meta['pdfTextLines']);
        $t->contains($expected, $blocks);
        $t->same($expected, $document->children[0]->children[0]->attr('text'));
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf derived predefined UTF8 source CMap fallback in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithDerivedPredefinedUtf8SourceFallbackWithoutToUnicode): void {
        $document = (new PdfReader())->read($pdfWithDerivedPredefinedUtf8SourceFallbackWithoutToUnicode());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $expected = html_entity_decode('&#x1F600;', ENT_QUOTES, 'UTF-8') . 'Wide';

        $t->same(1, $meta['pdfTextLines']);
        $t->contains($expected, $blocks);
        $t->same($expected, $document->children[0]->children[0]->attr('text'));
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf vertical predefined UTF8 source CMap inheritance in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithVerticalPredefinedUtf8SourceWithoutToUnicode): void {
        $document = (new PdfReader())->read($pdfWithVerticalPredefinedUtf8SourceWithoutToUnicode());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Vertical Text', $blocks);
        $t->same('Vertical Text', $document->children[0]->children[0]->attr('text'));
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf rotated Tm vectors for positioned text in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithRotatedTextMatrixForPositioning): void {
        $document = (new PdfReader())->read($pdfWithRotatedTextMatrixForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('Database Tool', $blocks);
        $t->contains('NextLine', $blocks);
        $t->true(!str_contains($blocks, 'Data base'));
        $t->same('Database Tool', $document->children[0]->children[0]->attr('text'));
        $t->same('NextLine', $document->children[1]->children[0]->attr('text'));
    },
    'uses pdf cm transformation matrices for positioned text in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithScaledCtmTextMatrixForPositioning): void {
        $document = (new PdfReader())->read($pdfWithScaledCtmTextMatrixForPositioning());
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('Data Tool', $blocks);
        $t->contains('PlainText', $blocks);
        $t->true(!str_contains($blocks, 'DataTool'));
        $t->true(!str_contains($blocks, 'Plain Text'));
        $t->same('Data Tool', $document->children[0]->children[0]->attr('text'));
        $t->same('PlainText', $document->children[1]->children[0]->attr('text'));
    },
    'does not promote non page font streams into pandoc ast text' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Readable PDF text) Tj ET';
        $fontProgram = "BT /F1 12 Tf 72 720 Td (\x91\x92) Tj T* (?PKEA) Tj T* (\x87\x88\x89\x8A\x8A\x8B\x88\x8C\x8B\x89) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FontDescriptor 6 0 R /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /Subset /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Readable PDF text', $blocks);
        $t->true(!str_contains($blocks, "\u{2018}\u{2019}"));
        $t->true(!str_contains($blocks, '?PKEA'));
    },
    'uses pdf actual text replacements for custom glyph page content' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /ActualText (Quarterly Risk Review) >> BDC <3F314749314541> Tj EMC T* "
            . "/Span /P1 BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /P1 << /ActualText (Project scope) >> >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Subset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->contains('Quarterly Risk Review', $blocks);
        $t->contains('Project scope', $blocks);
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, '?PKEA'));
    },
    'suppresses pdf marked content artifacts in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "/Artifact BMC (Running Header) Tj EMC T* "
            . "/F1 12 Tf /Span << /ActualText (Article Body) >> BDC <3F504B4541> Tj EMC T* "
            . "/F2 12 Tf /Art#69fact << /Type /Pagination >> BDC (Page 1) Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Article Body', $document->children[0]->attr('text'));
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->contains('<p>Article Body</p>', $blocks);
        $t->true(!str_contains($blocks, 'Running Header'));
        $t->true(!str_contains($blocks, 'Page 1'));
        $t->true(!str_contains($blocks, '?PKEA'));
    },
    'uses pdf structure tree ParentTree MCID ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Tagged Structure Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Tagged Line) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->contains('Tagged Structure Text', $blocks);
        $t->contains('Second Tagged Line', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses pdf escaped tagged dictionary names for ParentTree ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCI#44 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Struct#50arents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /Parent#54ree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nu#6Ds [ 0 [ 9 0 R null ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Actual#54ext (Escaped Key Tagged Text) /#4B << /Type /MCR /MCI#44 1 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Escaped Key Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Escaped Key Tagged Text'));
    },
    'uses pdf escaped StructTreeRoot catalog and type names for ParentTree ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Struct#54reeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Struct#54reeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Escaped Root Tagged Text) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Escaped Root Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Escaped Root Tagged Text'));
    },
    'uses pdf direct ParentTree Kids MCID ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree << /Limits [4 5] /Kids [ << /Limits [4 4] /Nums [ 4 [ 11 0 R ] ] >> << /Limits [5 5] /Nums [ 5 [ 9 0 R 10 0 R ] ] >> ] >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Direct Kid Tagged Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Direct Kid Line) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Wrong Parent Key) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Direct Kid Tagged Text', $blocks);
        $t->contains('Second Direct Kid Line', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, 'Wrong Parent Key'));
    },
    'uses pdf indirect ParentTree Kids Limits and Nums arrays in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Kids 14 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect Nums Tagged Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Indirect Nums Line) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Wrong Indirect Parent Key) >>\nendobj\n"
            . "14 0 obj\n[ 15 0 R 16 0 R ]\nendobj\n"
            . "15 0 obj\n<< /Limits 17 0 R /Nums 18 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Limits 19 0 R /Nums 20 0 R >>\nendobj\n"
            . "17 0 obj\n[4 4]\nendobj\n"
            . "18 0 obj\n[4 [ 11 0 R ]]\nendobj\n"
            . "19 0 obj\n[5 5]\nendobj\n"
            . "20 0 obj\n[5 [ 9 0 R 10 0 R ]]\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Indirect Nums Tagged Text', $blocks);
        $t->contains('Second Indirect Nums Line', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, 'Wrong Indirect Parent Key'));
    },
    'uses pdf indirect StructElem K arrays for ParentTree ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect K Array Tagged Text) /K 14 0 R >>\nendobj\n"
            . "14 0 obj\n[ << /Type /MCR /MCID 1 >> ]\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Indirect K Array Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Indirect K Array Tagged Text'));
    },
    'uses pdf indirect StructElem K integers for ParentTree ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect K Integer Tagged Text) /K 14 0 R >>\nendobj\n"
            . "14 0 obj\n1\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Indirect K Integer Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Indirect K Integer Tagged Text'));
    },
    'uses pdf StructTreeRoot K page scoped ActualText without ParentTree in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructElem /S /Document /K [ 9 0 R 10 0 R 11 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root K Integer Tagged Text) /K 0 >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Root K MCR Tagged Text) /K << /Type /MCR /Pg 3 0 R /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /Pg 12 0 R /ActualText (Wrong Page Tagged Text) /K 0 >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Root K Integer Tagged Text', $blocks);
        $t->contains('Root K MCR Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, 'Wrong Page Tagged Text'));
    },
    'uses pdf indirect StructTreeRoot K arrays for page scoped ActualText fallback in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n[ 9 0 R ]\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root Indirect K Array Tagged Text) /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n[ << /Type /MCR /Pg 3 0 R /MCID 1 >> ]\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Root Indirect K Array Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Root Indirect K Array Tagged Text'));
    },
    'uses pdf indirect StructTreeRoot K integers for page scoped ActualText fallback in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n[ 9 0 R ]\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root Indirect K Integer Tagged Text) /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n1\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Root Indirect K Integer Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Root Indirect K Integer Tagged Text'));
    },
    'uses pdf escaped StructTreeRoot catalog and type names for K ActualText fallback in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Struct#54reeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Struct#54reeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Escaped Root K Tagged Text) /K 0 >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Escaped Root K Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->same(1, substr_count($blocks, 'Escaped Root K Tagged Text'));
    },
    'uses pdf grouped StructElem ActualText across MCIDs in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Grouped Tagged Sentence) /K [ << /Type /MCR /MCID 0 >> << /Type /MCR /MCID 1 >> ] >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Grouped Tagged Sentence', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->same(1, substr_count($blocks, 'Grouped Tagged Sentence'));
    },
    'uses pdf nested StructElem descendant ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Sect /K [ 10 0 R 11 0 R ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /P /ActualText (Nested Tagged First) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /P /K 12 0 R >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /Span /ActualText (Nested Tagged Second) /K [ << /Type /MCR /MCID 1 >> ] >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Nested Tagged First', $blocks);
        $t->contains('Nested Tagged Second', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses pdf StructElem Alt and E fallbacks in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Alt (Alternate Tagged Text) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /E (Expanded Abbreviation Text) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Actual Text Wins) /Alt (Ignored Alternate Text) /E (Ignored Expanded Text) /K << /Type /MCR /MCID 2 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(3, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Alternate Tagged Text', $blocks);
        $t->contains('Expanded Abbreviation Text', $blocks);
        $t->contains('Actual Text Wins', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, 'Ignored Alternate Text'));
        $t->true(!str_contains($blocks, 'Ignored Expanded Text'));
    },
    'uses pdf indirect StructElem replacement strings in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText 12 0 R /Alt (Ignored Indirect Alternate Text) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /Alt 13 0 R /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /E 14 0 R /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "12 0 obj\n(Indirect ActualText Tagged Text)\nendobj\n"
            . "13 0 obj\n<496E64697265637420416C74205461676765642054657874>\nendobj\n"
            . "14 0 obj\n(Indirect Expanded Tagged Text)\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(3, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Indirect ActualText Tagged Text', $blocks);
        $t->contains('Indirect Alt Tagged Text', $blocks);
        $t->contains('Indirect Expanded Tagged Text', $blocks);
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
        $t->true(!str_contains($blocks, 'Ignored Indirect Alternate Text'));
        $t->same(1, substr_count($blocks, 'Indirect ActualText Tagged Text'));
        $t->same(1, substr_count($blocks, 'Indirect Alt Tagged Text'));
        $t->same(1, substr_count($blocks, 'Indirect Expanded Tagged Text'));
    },
    'uses pdf form XObject StructParents MCID ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $pageContent = "BT /Fpage 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET q /Fm1 Do Q";
        $formContent = "BT /Fform 12 Tf 20 20 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 8 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+PageSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /StructParents 1 /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+FormSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructTreeRoot /ParentTree 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Nums [ 0 [ 10 0 R ] 1 [ 11 0 R ] ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Page Tagged Text) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Form Tagged Text) >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Page Tagged Text', $blocks);
        $t->contains('Form Tagged Text', $blocks);
        $t->same(1, substr_count($blocks, 'Page Tagged Text'));
        $t->same(1, substr_count($blocks, 'Form Tagged Text'));
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses pdf indirect StructParents values for page and form ActualText in pandoc ast blocks' => static function (TestRunner $t): void {
        $pageContent = "BT /Fpage 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET q /Fm1 Do Q";
        $formContent = "BT /Fform 12 Tf 20 20 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 8 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 12 0 R /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+PageSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /StructParents 13 0 R /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+FormSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructTreeRoot /ParentTree 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Nums [ 0 [ 10 0 R ] 1 [ 11 0 R ] ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Page Indirect StructParents Text) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Form Indirect StructParents Text) >>\nendobj\n"
            . "12 0 obj\n0\nendobj\n"
            . "13 0 obj\n1\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Page Indirect StructParents Text', $blocks);
        $t->contains('Form Indirect StructParents Text', $blocks);
        $t->same(1, substr_count($blocks, 'Page Indirect StructParents Text'));
        $t->same(1, substr_count($blocks, 'Form Indirect StructParents Text'));
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
    'uses pdf descendant CIDFont ToUnicode maps in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0001> <00440065007300630065006E00640061006E00740020004300490044>\n"
            . "<0002> <0020004D00610070>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DescendantCIDMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CompositeSubset /Encoding /Identity-H /DescendantFonts [5 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CompositeSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /ToUnicode 6 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Descendant CID Map', $blocks);
        $t->true(!str_contains($blocks, "\x00\x01"));
        $t->true(!str_contains($blocks, "\x00\x02"));
    },
    'uses pdf predictor encoded ToUnicode CMap streams in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0102> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<01> <0057006F0072006400500072006500730073>\n"
            . "<02> <00200050007200650064006900630074006F0072>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredictorToUnicodeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $columns = 17;
        $encodedRows = '';
        foreach (str_split($cmap, $columns) as $row) {
            $encodedRows .= "\0" . str_pad($row, $columns, ' ');
        }
        $encodedCmap = gzcompress($encodedRows);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredictorToUnicodeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns {$columns} >> /Length " . strlen($encodedCmap) . " >>\nstream\n{$encodedCmap}endstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->same(0, $meta['pdfFailedStreams']);
        $t->contains('WordPress Predictor', $blocks);
    },
    'uses pdf odd-nibble ToUnicode CMap hex strings in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <105070> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<1> <7>\n"
            . "endcodespacerange\n"
            . "3 beginbfchar\n"
            . "<1> <005>\n"
            . "<5> <00440046>\n"
            . "<7> <0020004F004B>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /OddNibbleToUnicodeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddNibbleSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, "\x10"));
    },
    'uses pdf ToUnicode bfrange array targets in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "1 beginbfrange\n"
            . "<0001> <0004> [ <0057006F00720064> <00500072006500730073> <002000410072007200610079> <002000520061006E00670065> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /ArrayRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ArrayRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('WordPress Array Range', $blocks);
        $t->true(!str_contains($blocks, "\x00\x05"));
    },
    'uses pdf ToUnicode sequential bfrange multi-codepoint targets in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001000200030004> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "1 beginbfrange\n"
            . "<0001> <0003> <0057006F007200640031>\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /SequentialWideRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SequentialWideRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Word1Word2Word3', $blocks);
        $t->true(!str_contains($blocks, "\x00\x04"));
    },
    'ignores pdf ToUnicode bfrange entries with mismatched source widths in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <018000028001> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 begincodespacerange\n"
            . "<00> <7F>\n"
            . "<8000> <FFFF>\n"
            . "endcodespacerange\n"
            . "3 beginbfrange\n"
            . "<01> <8000> <0042>\n"
            . "<02> <8001> [ <0043> ]\n"
            . "<8000> <8001> [ <0057006F0072006400500072006500730073> <00200042006C006F0063006B0073> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /MismatchedRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MismatchedRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('WordPress Blocks', $blocks);
        $t->true(!str_contains($blocks, 'BWordPress'));
        $t->true(!str_contains($blocks, 'C Blocks'));
        $t->true(!str_contains($blocks, "\x01"));
        $t->true(!str_contains($blocks, "\x02"));
    },
    'ignores pdf ToUnicode CMap PostScript comments in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001000200030004> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "% /WMode 1\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "% <00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0001> <0057006F00720064>\n"
            . "% <0001> <0042>\n"
            . "<0002> <0050007200650073>\n"
            . "% <0002> <0043>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<0003> <0004> [ <007300200043006F006D006D0065> <006E00740073> ]\n"
            . "% <0003> <0004> [ <0020004200610064> <0020004D00610070> ]\n"
            . "endbfrange\n"
            . "% /CMapName /CommentOverrideCMap def\n"
            . "endcmap\n"
            . "CMapName currentdict /CommentSafeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentedCMapSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('WordPress Comments', $blocks);
        $t->true(!str_contains($blocks, 'BC'));
        $t->true(!str_contains($blocks, 'Bad Map'));
    },
    'uses pdf ToUnicode CMap literal string targets in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "3 beginbfchar\n"
            . "<0001> (Word)\n"
            . "<0002> (Press)\n"
            . "<0003> (\\376\\377\\000!)\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<0004> <0005> [( Literal) ( Target)]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralTargetsCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralTargetsSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('WordPress! Literal Target', $blocks);
        $t->true(!str_contains($blocks, "\0"));
        $t->same('WordPress! Literal Target', $document->children[0]->children[0]->attr('text'));
    },
    'uses pdf ToUnicode CMap literal source operands in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <41424344> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "(A) (Z)\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "(A) <0057006F00720064>\n"
            . "(B) <0050007200650073>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "(C) (D) [ <00730020004C00690074> <006500720061006C> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourcesCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourcesSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('WordPress Literal', $blocks);
        $t->true(!str_contains($blocks, "\0"));
        $t->true(!str_contains($blocks, 'ABCD'));
        $t->same('WordPress Literal', $document->children[0]->children[0]->attr('text'));
    },
    'uses pdf Unicode CID CMap encodings without ToUnicode in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005000600070008> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "5 begincidchar\n"
            . "<0001> 87\n"
            . "<0002> 111\n"
            . "<0003> 114\n"
            . "<0004> 100\n"
            . "<0005> 32\n"
            . "endcidchar\n"
            . "1 begincidrange\n"
            . "<0006> <0008> 65\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /Adobe-Identity-UCS defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnicodeCID /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /UnicodeCID /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Word ABC', $blocks);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf Unicode CID CMap literal source operands without ToUnicode in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <41424344> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceUCS def\n"
            . "1 begincodespacerange\n"
            . "(A) (Z)\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "(A) (C) 65\n"
            . "endcidrange\n"
            . "1 begincidchar\n"
            . "(D) 33\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceUCS defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceUCS /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralSourceUCS /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('ABC!', $blocks);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
    },
    'uses pdf vertical CID writing mode in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 1 0 0 1 100 700 Tm <0001> Tj 1 0 0 1 100 694 Tm <0002> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/WMode 1\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0001> <0056006500720074006900630061006C>\n"
            . "<0002> <00200054006500780074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /VerticalCIDMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalCID /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Vertical Text', $blocks);
    },
    'does not emit blocks from Identity CID fonts without Unicode maps' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj T* /Fvert 12 Tf 72 700 Td <0003> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /Fvert 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NoUnicodeH /Encoding /Identity-H /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NoUnicodeV /Encoding /Identity-V /DescendantFonts [8 0 R] >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NoUnicodeH /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NoUnicodeV /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfTextLines']);
        $t->same(['Fcid', 'Fvert'], $meta['pdfMissingUnicodeFonts']);
        $t->same(['Fcid' => 'Identity-H', 'Fvert' => 'Identity-V'], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(2, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('font lacks a Unicode map', implode("\n", $meta['pdfWarnings']));
        $t->same('', trim($blocks));
        $t->true(!str_contains($blocks, "\x00\x01"));
        $t->true(!str_contains($blocks, "\x00\x02"));
        $t->true(!str_contains($blocks, "\x00\x03"));
    },
    'does not emit blocks from embedded CID CMap fonts without Unicode maps' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CMapName /EmbeddedCIDMap def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "endcmap\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EmbeddedCID /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /EmbeddedCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfTextLines']);
        $t->same(['Fcid'], $meta['pdfMissingUnicodeFonts']);
        $t->same(['Fcid' => 'EmbeddedCIDMap'], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(1, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('font lacks a Unicode map', implode("\n", $meta['pdfWarnings']));
        $t->same('', trim($blocks));
        $t->true(!str_contains($blocks, "\x00\x01"));
        $t->true(!str_contains($blocks, "\x00\x02"));
    },
    'promotes pdf form XObject text into pandoc ast blocks' => static function (TestRunner $t): void {
        $pageContent = 'BT /Fpage 12 Tf 72 720 Td (Before form) Tj ET q /Fm1 Do Q q /Im1 Do Q BT /Fpage 12 Tf 72 680 Td (After form) Tj ET';
        $formContent = 'BT /Fform 12 Tf 20 20 Td (Form XObject text) Tj ET';
        $imagePayload = 'BT /Fform 12 Tf 20 20 Td (Image payload text) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R /Im1 8 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(3, $meta['pdfTextLines']);
        $t->same(['Image'], $meta['pdfIgnoredXObjectSubtypes']);
        $t->same(1, $meta['pdfIgnoredXObjectCount']);
        $t->contains('Ignored 1 non-text PDF XObject(s): Image.', implode("\n", $meta['pdfWarnings']));
        $beforeOffset = strpos($blocks, 'Before form');
        $formOffset = strpos($blocks, 'Form XObject text');
        $afterOffset = strpos($blocks, 'After form');
        $t->true($beforeOffset !== false);
        $t->true($formOffset !== false);
        $t->true($afterOffset !== false);
        $t->true($beforeOffset < $formOffset);
        $t->true($formOffset < $afterOffset);
        $t->true(!str_contains($blocks, 'Image payload text'));
    },
    'keeps pdf page font resource scopes separate in pandoc ast blocks' => static function (TestRunner $t): void {
        $firstPageContent = 'BT /F1 12 Tf 72 720 Td <01> Tj ET';
        $secondPageContent = 'BT /F1 12 Tf 72 720 Td <01> Tj ET';
        $firstCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "1 beginbfchar\n"
            . "<01> <00500061006700650020004F006E0065>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $secondCMap = str_replace('00500061006700650020004F006E0065', '0050006100670065002000540077006F', $firstCMap);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FirstSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SecondSubset /Encoding /Identity-H /ToUnicode 10 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($firstCMap) . " >>\nstream\n{$firstCMap}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Length " . strlen($secondCMap) . " >>\nstream\n{$secondCMap}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->contains('Page One', $blocks);
        $t->contains('Page Two', $blocks);
        $t->true(strpos($blocks, 'Page One') < strpos($blocks, 'Page Two'));
    },
    'uses pdf ToUnicode UseCMap inheritance in pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <010203> Tj ET';
        $baseCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<01> <0057006F0072006400500072006500730073>\n"
            . "<02> <00200049006D0070006F00720074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /BaseImportCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/BaseImportCMap usecmap\n"
            . "1 beginbfchar\n"
            . "<03> <00200042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DerivedImportCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapSubset /Encoding /Identity-H /ToUnicode 4 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('WordPress Import Blocks', $blocks);
    },
    'reads pdf page and font dictionaries from compressed object streams into pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithCompressedObjectStream): void {
        $document = (new PdfReader())->read($pdfWithCompressedObjectStream());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Object Stream Text', $blocks);
    },
    'uses pdf newer object-stream revisions over stale regular objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithIncrementalObjectStreamUpdate): void {
        $document = (new PdfReader())->read($pdfWithIncrementalObjectStreamUpdate());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Incremental Object Stream Text', $blocks);
        $t->true(!str_contains($blocks, 'Old Revision Text'));
    },
    'uses pdf catalog page tree instead of stale orphan page objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithCatalogReachableAndOrphanPages): void {
        $document = (new PdfReader())->read($pdfWithCatalogReachableAndOrphanPages());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Catalog Reachable Page', $blocks);
        $t->true(!str_contains($blocks, 'Stale Orphan Page'));
    },
    'uses pdf referenced object generation instead of stale same-number objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithGeneratedContentReference): void {
        $document = (new PdfReader())->read($pdfWithGeneratedContentReference());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Generation One Content', $blocks);
        $t->true(!str_contains($blocks, 'Generation Zero Content'));
    },
    'skips pdf xref free content objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithXrefFreedContentObject): void {
        $document = (new PdfReader())->read($pdfWithXrefFreedContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Xref Current Content', $blocks);
        $t->true(!str_contains($blocks, 'Freed Stale Content'));
    },
    'uses pdf xref in-use offsets instead of later duplicate objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithXrefSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithXrefSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Xref Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Later Duplicate Content'));
    },
    'uses earlier valid pdf startxref when a malformed appended update follows in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithEarlierValidStartxrefAfterMalformedAppend): void {
        $document = (new PdfReader())->read($pdfWithEarlierValidStartxrefAfterMalformedAppend());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([999999], $meta['pdfDiagnostics']['malformedXrefOffsets']);
        $t->contains('Earlier Valid Xref Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Bad Append Duplicate'));
    },
    'uses pdf xref stream in-use offsets instead of later duplicate objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithXrefStreamSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithXrefStreamSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Xref Stream Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Xref Stream Duplicate'));
    },
    'decodes predictor pdf xref streams in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithPredictorXrefStreamSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithPredictorXrefStreamSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Predictor Xref Stream Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Predictor Xref Stream Duplicate'));
    },
    'uses pdf xref stream zero-width type and generation defaults in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Zero Width Xref Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Zero Width Xref Duplicate'));
    },
    'uses indirect pdf xref stream W and Index arrays in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Indirect Xref Arrays Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Indirect Xref Arrays Duplicate'));
    },
    'uses latest indirect pdf xref stream W and Index array objects in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Latest Indirect Xref Arrays Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Latest Indirect Xref Arrays Duplicate'));
    },
    'uses latest indirect pdf xref stream Size object in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Latest Indirect Xref Size Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Latest Indirect Xref Size Duplicate'));
    },
    'follows indirect pdf Prev xref offsets in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithIndirectPrevXrefChainSelectedContentObject): void {
        $document = (new PdfReader())->read($pdfWithIndirectPrevXrefChainSelectedContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfDiagnostics']['malformedXrefOffsets']);
        $t->contains('Indirect Prev Chain Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Indirect Prev Duplicate'));
    },
    'follows pdf Prev xref offsets after nested trailer dictionaries in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithNestedTrailerPrevXrefChainSelectedContentObject): void {
        $document = (new PdfReader())->read($pdfWithNestedTrailerPrevXrefChainSelectedContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfDiagnostics']['malformedXrefOffsets']);
        $t->contains('Nested Trailer Prev Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Nested Trailer Duplicate'));
    },
    'follows indirect pdf XRefStm offsets in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithIndirectXRefStmHybridSelectedContentObject): void {
        $document = (new PdfReader())->read($pdfWithIndirectXRefStmHybridSelectedContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedXrefStreams']);
        $t->contains('Indirect XRefStm Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Ignored Indirect XRefStm Duplicate'));
    },
    'uses pdf xref stream type two object stream entries in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithXrefStreamCompressedPageSelection): void {
        $document = (new PdfReader())->read($pdfWithXrefStreamCompressedPageSelection());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Xref Stream Packed Page', $blocks);
        $t->true(!str_contains($blocks, 'Xref Stream Stale Packed Page'));
    },
    'decodes predictor pdf object streams in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithPredictorObjectStreamSelection): void {
        $document = (new PdfReader())->read($pdfWithPredictorObjectStreamSelection());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['failedStreams']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->contains('Predictor ObjStm Packed Page', $blocks);
        $t->true(!str_contains($blocks, 'Predictor ObjStm Stale Page'));
    },
    'resolves indirect pdf object stream N and First values in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithIndirectObjectStreamHeaderSelection): void {
        $document = (new PdfReader())->read($pdfWithIndirectObjectStreamHeaderSelection());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same(0, $meta['pdfDiagnostics']['malformedObjectStreams']);
        $t->contains('Indirect ObjStm Header Packed Page', $blocks);
        $t->true(!str_contains($blocks, 'Indirect ObjStm Header Stale Page'));
    },
    'uses pdf hybrid xref stream offsets over stale table offsets in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamSelectedDuplicateContentObject): void {
        $document = (new PdfReader())->read($pdfWithHybridXrefStreamSelectedDuplicateContentObject());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Hybrid Xref Stream Selected Content', $blocks);
        $t->true(!str_contains($blocks, 'Hybrid Table Duplicate Content'));
    },
    'uses pdf hybrid xref stream type two object stream entries in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamCompressedPageSelection): void {
        $document = (new PdfReader())->read($pdfWithHybridXrefStreamCompressedPageSelection());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Hybrid Xref Stream Packed Page', $blocks);
        $t->true(!str_contains($blocks, 'Hybrid Xref Stream Stale Packed Page'));
    },
    'follows pdf hybrid xref table and stream previous branches in pandoc ast blocks' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamAndTablePreviousBranches): void {
        $document = (new PdfReader())->read($pdfWithHybridXrefStreamAndTablePreviousBranches());
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('Hybrid Prev Branch Text', $blocks);
    },
    'decodes pdf Symbol and ZapfDingbats fonts into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Fsym 12 Tf 72 720 Td <41426162B3B9BA> Tj T* /Fzap 12 Tf <3334A8AAAB> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsym 4 0 R /Fzap 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Symbol >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ZapfDingbats /Encoding /DingbatsEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->contains("\u{0391}\u{0392}\u{03B1}\u{03B2}\u{2265}\u{2260}\u{2261}", $blocks);
        $t->contains("\u{2713}\u{2714}\u{2663}\u{2665}\u{2660}", $blocks);
    },
    'decodes pdf Type3 and embedded Type1 glyph-name encodings into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Ftype3 12 Tf 72 720 Td <4142434445> Tj T* /Fsubset 12 Tf <5051527A205354> Tj ET';
        $charProc = '0 0 500 700 re f';
        $fontProgram = "%!PS-AdobeFont-1.0: CustomSubset 1.0\n"
            . "/Encoding 256 array\n"
            . "0 1 255 {1 index exch /.notdef put} for\n"
            . "dup 32 /space put\n"
            . "dup 80 /P put\n"
            . "dup 81 /D put\n"
            . "dup 82 /F put\n"
            . "dup 83 /O put\n"
            . "dup 84 /K put\n"
            . "readonly def\n"
            . "eexec\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Ftype3 4 0 R /Fsubset 7 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ftype3 /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 69 /Widths [500 500 500 500 500] /Encoding << /Type /Encoding /Differences [ 65 /H /e /l /l /o ] >> /CharProcs << /H 6 0 R /e 6 0 R /l 6 0 R /o 6 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /FontDescriptor 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+CustomSubset /FontFile 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Hello', $blocks);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
    },
    'decodes pdf Type3 StandardEncoding glyph names into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = 'BT /Ftype3 12 Tf 72 720 Td <414520AE20AF> Tj ET';
        $charProc = '0 0 0 0 0 0 d1';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Ftype3 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ftype3 /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 32 /LastChar 175 /Widths [500] /Encoding /StandardEncoding /CharProcs << /A 6 0 R /E 6 0 R /space 6 0 R /fi 6 0 R /fl 6 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->contains('AE fi fl', $blocks);
        $t->true(!str_contains($blocks, "\u{00AE}"));
        $t->true(!str_contains($blocks, "\u{00AF}"));
    },
    'decodes pdf Type3 explicit base encodings into pandoc ast blocks' => static function (TestRunner $t): void {
        $content = "BT /Fwin 12 Tf 72 720 Td <576F72645072657373927320636166E9> Tj "
            . "T* /Fmac 12 Tf <576F72645072657373204D6163526F6D616E3A208E2044617461D120DB> Tj "
            . "T* /Fsym 12 Tf <41426162B3B9BA> Tj "
            . "T* /Fzap 12 Tf <3334A8AAAB> Tj ET";
        $charProc = '0 0 0 0 0 0 d1';
        $charProcs = '<< /A 9 0 R /B 9 0 R /W 9 0 R /o 9 0 R /r 9 0 R /d 9 0 R /P 9 0 R /e 9 0 R /s 9 0 R /quoteright 9 0 R /c 9 0 R /a 9 0 R /f 9 0 R /eacute 9 0 R /colon 9 0 R /emdash 9 0 R /Euro 9 0 R /quotedblleft 9 0 R /quotedblright 9 0 R /Alpha 9 0 R /Beta 9 0 R /alpha 9 0 R /beta 9 0 R /greaterequal 9 0 R /notequal 9 0 R /equivalence 9 0 R /a19 9 0 R /a20 9 0 R /club 9 0 R /heart 9 0 R /spade 9 0 R >>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fwin 4 0 R /Fmac 5 0 R /Fsym 6 0 R /Fzap 7 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fwin /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fmac /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding << /Type /Encoding /BaseEncoding /MacRomanEncoding >> /CharProcs {$charProcs} >>\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fsym /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /SymbolEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fzap /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /ZapfDingbatsEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(4, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains("WordPress\u{2019}s caf\u{00E9}", $blocks);
        $t->contains("WordPress MacRoman: é Data— €", $blocks);
        $t->contains("\u{0391}\u{0392}\u{03B1}\u{03B2}\u{2265}\u{2260}\u{2261}", $blocks);
        $t->contains("\u{2713}\u{2714}\u{2663}\u{2665}\u{2660}", $blocks);
    },
    'decodes pdf embedded TrueType cmap encodings into pandoc ast blocks' => static function (TestRunner $t) use ($trueTypeWithFormat4CMap): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <4869204F4B7A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMap([0x20, 0x48, 0x69, 0x4F, 0x4B]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+TinyTrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+TinyTrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('Hi OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
    },
    'decodes pdf embedded TrueType post glyph names into pandoc ast blocks' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPostNames([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+PostNamedTrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+PostNamedTrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $blocks));
    },
    'decodes pdf embedded TrueType post 1.0 standard glyph names into pandoc ast blocks' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPost10Names): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPost10Names([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Post10TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Post10TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $blocks));
    },
    'decodes pdf embedded TrueType post 2.5 glyph names into pandoc ast blocks' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPost25Names): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPost25Names([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Post25TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Post25TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $blocks));
    },
    'decodes pdf embedded TrueType compact trimmed and grouped cmap formats into pandoc ast blocks' => static function (TestRunner $t) use ($trueTypeWithFormat0CMapAndPostNames, $trueTypeWithFormat6CMapAndPostNames, $trueTypeWithFormat8CMapAndPostNames, $trueTypeWithFormat10CMapAndPostNames, $trueTypeWithFormat13CMapAndPostNames): void {
        $format0Content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $format0Program = $trueTypeWithFormat0CMapAndPostNames([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $format0Pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format0TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($format0Content) . " >>\nstream\n{$format0Content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format0TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($format0Program) . " >>\nstream\n{$format0Program}\nendstream\nendobj\n%%EOF";

        $format0Document = (new PdfReader())->read($format0Pdf);
        $format0Blocks = PandocConverter::write($format0Document, 'blocks');
        $format0Meta = $format0Document->attr('meta');

        $t->same(1, $format0Meta['pdfTextLines']);
        $t->same([], $format0Meta['pdfMissingUnicodeFonts']);
        $t->same(0, $format0Meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $format0Blocks);
        $t->true(!str_contains($format0Blocks, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $format0Blocks));

        $format6Content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $format6Program = $trueTypeWithFormat6CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $format6Pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format6TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($format6Content) . " >>\nstream\n{$format6Content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format6TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($format6Program) . " >>\nstream\n{$format6Program}\nendstream\nendobj\n%%EOF";

        $format6Document = (new PdfReader())->read($format6Pdf);
        $format6Blocks = PandocConverter::write($format6Document, 'blocks');
        $format6Meta = $format6Document->attr('meta');

        $t->same(1, $format6Meta['pdfTextLines']);
        $t->same([], $format6Meta['pdfMissingUnicodeFonts']);
        $t->same(0, $format6Meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $format6Blocks);
        $t->true(!str_contains($format6Blocks, 'z'));
        $t->true(!str_contains($format6Blocks, '012345'));

        $format10Content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $format10Program = $trueTypeWithFormat10CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $format10Pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format10TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($format10Content) . " >>\nstream\n{$format10Content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format10TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($format10Program) . " >>\nstream\n{$format10Program}\nendstream\nendobj\n%%EOF";

        $format10Document = (new PdfReader())->read($format10Pdf);
        $format10Blocks = PandocConverter::write($format10Document, 'blocks');
        $format10Meta = $format10Document->attr('meta');

        $t->same(1, $format10Meta['pdfTextLines']);
        $t->same([], $format10Meta['pdfMissingUnicodeFonts']);
        $t->same(0, $format10Meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $format10Blocks);
        $t->true(!str_contains($format10Blocks, 'z'));
        $t->true(!str_contains($format10Blocks, '012345'));

        $format8Content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $format8Program = $trueTypeWithFormat8CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $format8Pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format8TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($format8Content) . " >>\nstream\n{$format8Content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format8TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($format8Program) . " >>\nstream\n{$format8Program}\nendstream\nendobj\n%%EOF";

        $format8Document = (new PdfReader())->read($format8Pdf);
        $format8Blocks = PandocConverter::write($format8Document, 'blocks');
        $format8Meta = $format8Document->attr('meta');

        $t->same(1, $format8Meta['pdfTextLines']);
        $t->same([], $format8Meta['pdfMissingUnicodeFonts']);
        $t->same(0, $format8Meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $format8Blocks);
        $t->true(!str_contains($format8Blocks, 'z'));
        $t->true(!str_contains($format8Blocks, '012345'));

        $format13Content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $format13Program = $trueTypeWithFormat13CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $format13Pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format13TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($format13Content) . " >>\nstream\n{$format13Content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format13TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($format13Program) . " >>\nstream\n{$format13Program}\nendstream\nendobj\n%%EOF";

        $format13Document = (new PdfReader())->read($format13Pdf);
        $format13Blocks = PandocConverter::write($format13Document, 'blocks');
        $format13Meta = $format13Document->attr('meta');

        $t->same(1, $format13Meta['pdfTextLines']);
        $t->same([], $format13Meta['pdfMissingUnicodeFonts']);
        $t->same(0, $format13Meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $format13Blocks);
        $t->true(!str_contains($format13Blocks, 'z'));
        $t->true(!str_contains($format13Blocks, '012345'));
    },
    'decodes pdf embedded CFF Type1C encodings into pandoc ast blocks' => static function (TestRunner $t) use ($cffType1CWithFormat0Encoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <5051527A205354> Tj ET';
        $fontProgram = $cffType1CWithFormat0Encoding([
            0x50 => 'P',
            0x51 => 'D',
            0x52 => 'F',
            0x20 => 'space',
            0x53 => 'O',
            0x54 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TinyCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+TinyCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
    },
    'decodes pdf embedded CFF Type1C predefined encodings into pandoc ast blocks' => static function (TestRunner $t) use ($cffType1CWithDefaultStandardEncoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <504446204F4B7A> Tj ET';
        $fontProgram = $cffType1CWithDefaultStandardEncoding(50);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DefaultCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+DefaultCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
    },
    'decodes pdf embedded CFF Type1C Expert encodings into pandoc ast blocks' => static function (TestRunner $t) use ($cffType1CWithPredefinedCharsetAndEncoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <C9CACB2FBD80> Tj ET';
        $fontProgram = $cffType1CWithPredefinedCharsetAndEncoding(1, 1, 180);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ExpertCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+ExpertCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains("\u{00B9}\u{00B2}\u{00B3}\u{2044}\u{00BD}", $blocks);
        $t->true(!str_contains($blocks, "\x80"));
    },
    'decodes pdf embedded CID-keyed CFF Type0C UCS charsets into pandoc ast blocks' => static function (TestRunner $t) use ($cffCidKeyedType0CWithFormat0Charset): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0050004400460020004F004B007A> Tj ET';
        $fontProgram = $cffCidKeyedType0CWithFormat0Charset([0x50, 0x44, 0x46, 0x20, 0x4F, 0x4B]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEF+CidCFF /Encoding /Identity-H /DescendantFonts [6 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType0 /BaseFont /ABCDEF+CidCFF /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> /FontDescriptor 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+CidCFF /FontFile3 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Subtype /CIDFontType0C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(1, $meta['pdfTextLines']);
        $t->same([], $meta['pdfMissingUnicodeFonts']);
        $t->same([], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(0, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('PDF OK', $blocks);
        $t->true(!str_contains($blocks, 'z'));
    },
    'does not emit blocks from unmapped custom subset glyph streams' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "(stuvwxyz) Tj T* "
            . "(?PKEA) Tj T* "
            . "<8788898A8A8B888C8B898D8B8A8B8C8A8E8F8F8D888B8987> Tj T* "
            . "(?1GI1EA) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

        $document = (new PdfReader())->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfTextLines']);
        $t->same(['F1'], $meta['pdfMissingUnicodeFonts']);
        $t->same(['F1' => 'WinAnsiEncoding'], $meta['pdfMissingUnicodeFontEncodings']);
        $t->same(4, $meta['pdfSuppressedGlyphRuns']);
        $t->contains('suppressed because their font lacks a Unicode map', implode("\n", $meta['pdfWarnings']));
        $t->same('', trim($blocks));
        $t->true(!str_contains($blocks, 'stuvwxyz'));
        $t->true(!str_contains($blocks, '?PKEA'));
        $t->true(!str_contains($blocks, '?1GI1EA'));
    },
];
