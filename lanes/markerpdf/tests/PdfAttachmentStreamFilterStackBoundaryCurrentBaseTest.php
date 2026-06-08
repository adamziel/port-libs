<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentStreamFilterStackBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

$attachmentStreamFilterStackBoundaryCurrentBaseLzwLiteral = static function (string $bytes, bool $includeEndCode = true, string $suffix = ''): string {
    if (strlen($bytes) > 240) {
        throw new RuntimeException('Focused attachment LZW fixture must keep 9-bit literal codes.');
    }

    $codes = array_merge([256], array_map('ord', str_split($bytes)));
    if ($includeEndCode) {
        $codes[] = 257;
    }

    $bits = '';
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 511) {
            throw new RuntimeException('Focused attachment LZW fixture uses invalid 9-bit code.');
        }

        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }

        $encoded .= chr(bindec($byte));
    }

    return $encoded . $suffix;
};

$attachmentStreamFilterStackBoundaryCurrentBaseAsciiHex = static function (string $bytes): string {
    return strtoupper(bin2hex($bytes)) . '>';
};

$attachmentStreamFilterStackBoundaryCurrentBasePdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Identity Attachment Review) Tj ET';
    $identityPayload = "Title,Status\nIdentity Crypt Attachment,Ready\n";
    $privatePayload = "Title,Status\nPrivate Crypt Leak,Blocked\n";
    $identityEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($identityPayload));
    $privateEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($privatePayload));
    $identityChecksum = md5($identityPayload);
    $privateChecksum = md5($privatePayload);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(identity-stack.csv) 10 0 R (private-stack.csv) 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (identity-stack.csv) /Desc (Identity Crypt attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /Identity >> null null ] /Params << /Size " . strlen($identityPayload) . " /CheckSum <{$identityChecksum}> >> /Length " . strlen($identityEncoded) . " >>\nstream\n{$identityEncoded}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (private-stack.csv) /Desc (Private Crypt attachment stack) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /PrivateCF >> null null ] /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privateEncoded) . " >>\nstream\n{$privateEncoded}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
};

$attachmentStreamFilterStackBoundaryCurrentBaseEodCommentPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment EOD Comment Review) Tj ET';
    $payload = "Title,Status\nEOD Comment Attachment,Ready\n";
    $compressed = gzcompress($payload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused attachment EOD comment payload.');
    }

    $encoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($compressed)
        . '% attachment filter comment reaches the stream boundary';
    $checksum = md5($payload);

    return [
        'payload' => $payload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(eod-comment-stack.csv) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (eod-comment-stack.csv) /Desc (EOD comment attachment stack) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseFilterWhitespacePdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAsciiHex
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Filter Whitespace Review) Tj ET';
    $badPayload = "Title,Status\nVertical Tab Stack Attachment Leak,Blocked\n";
    $goodPayload = "Title,Status\nClean Stack Attachment,Ready\n";
    $badCompressed = gzcompress($badPayload);
    $goodCompressed = gzcompress($goodPayload);
    if (!is_string($badCompressed) || !is_string($goodCompressed)) {
        throw new RuntimeException('Unable to compress focused attachment filter whitespace fixture.');
    }

    $badEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAsciiHex($badCompressed);
    $badEncoded = substr($badEncoded, 0, 12) . "\x0b" . substr($badEncoded, 12);
    $goodEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAsciiHex($goodCompressed);

    return [
        'bad_payload' => $badPayload,
        'good_payload' => $goodPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(bad-whitespace.csv) 10 0 R (clean-stack.csv) 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (bad-whitespace.csv) /Desc (Invalid filter whitespace attachment stack) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($badPayload) . " /CheckSum <" . md5($badPayload) . "> >> /Length " . strlen($badEncoded) . " >>\nstream\n{$badEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (clean-stack.csv) /Desc (Clean attachment stack) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($goodPayload) . " /CheckSum <" . md5($goodPayload) . "> >> /Length " . strlen($goodEncoded) . " >>\nstream\n{$goodEncoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseDictionaryFilterPdf = static function (): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Malformed Filter Review) Tj ET';
    $payload = "Title,Status\nDictionary Filter Attachment Leak,Blocked\n";
    $checksum = md5($payload);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(dict-filter.csv) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (dict-filter.csv) /Desc (Malformed dictionary filter attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter << /Name /FlateDecode >> /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
};

$attachmentStreamFilterStackBoundaryCurrentBaseDuplicateStreamKeyPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Duplicate Stream Key Review) Tj ET';
    $duplicateFilterPayload = "Title,Status\nDuplicate Filter Attachment Leak,Blocked\n";
    $duplicateDecodeParmsPayload = "Title,Status\nDuplicate DecodeParms Attachment Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Attachment After Duplicate Keys,Ready\n";

    $duplicateFilterEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($duplicateFilterPayload));
    $duplicateDecodeParmsEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($duplicateDecodeParmsPayload));
    $validEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($validPayload));

    return [
        'duplicate_filter_payload' => $duplicateFilterPayload,
        'duplicate_decodeparms_payload' => $duplicateDecodeParmsPayload,
        'valid_payload' => $validPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(duplicate-filter.csv) 10 0 R (duplicate-decodeparms.csv) 12 0 R (valid-after-duplicates.csv) 14 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (duplicate-filter.csv) /Desc (Duplicate Filter attachment stream) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($duplicateFilterPayload) . " /CheckSum <" . md5($duplicateFilterPayload) . "> >> /Length " . strlen($duplicateFilterEncoded) . " >>\nstream\n{$duplicateFilterEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (duplicate-decodeparms.csv) /Desc (Duplicate DecodeParms attachment stream) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Columns 1 >> ] /DecodeParms [ null null ] /Params << /Size " . strlen($duplicateDecodeParmsPayload) . " /CheckSum <" . md5($duplicateDecodeParmsPayload) . "> >> /Length " . strlen($duplicateDecodeParmsEncoded) . " >>\nstream\n{$duplicateDecodeParmsEncoded}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Filespec /F (valid-after-duplicates.csv) /Desc (Valid attachment after duplicate stream keys) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseExtraFilterOperandPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Extra Filter Operand Review) Tj ET';
    $referencePayload = "Title,Status\nExtra Filter Reference Operand Leak,Blocked\n";
    $nullPayload = "Title,Status\nExtra Filter Null Operand Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Attachment After Extra Filter Operand,Ready\n";

    $referenceEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($referencePayload);
    $validCompressed = gzcompress($validPayload);
    if (!is_string($validCompressed)) {
        throw new RuntimeException('Unable to compress focused attachment extra-filter fixture.');
    }
    $validEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($validCompressed);

    return [
        'reference_payload' => $referencePayload,
        'null_payload' => $nullPayload,
        'valid_payload' => $validPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(extra-filter-reference.csv) 10 0 R (extra-filter-null.csv) 12 0 R (valid-after-extra-filter.csv) 14 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (extra-filter-reference.csv) /Desc (Extra filter reference operand attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 20 0 R /FlateDecode null /Params << /Size " . strlen($referencePayload) . " /CheckSum <" . md5($referencePayload) . "> >> /Length " . strlen($referenceEncoded) . " >>\nstream\n{$referenceEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (extra-filter-null.csv) /Desc (Extra filter null operand attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter null /ASCII85Decode null /Params << /Size " . strlen($nullPayload) . " /CheckSum <" . md5($nullPayload) . "> >> /Length " . strlen($nullPayload) . " >>\nstream\n{$nullPayload}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Filespec /F (valid-after-extra-filter.csv) /Desc (Valid attachment after extra filter operand) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
            . "20 0 obj\n/ASCII85Decode\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseAllNullPdf = static function (): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment All Null Stack Review) Tj ET';
    $payload = "Title,Status\nAll Null Attachment,Ready\n";
    $checksum = md5($payload);

    return [
        'payload' => $payload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(all-null-attachment.csv) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (all-null-attachment.csv) /Desc (All-null attachment filter stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ null ] /DecodeParms [ 99 0 R 100 0 R ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "99 0 obj\n<< /Predictor 12 /Columns 5 >>\nendobj\n"
            . "100 0 obj\n(All Null Attachment DecodeParms Leak)\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseLzwPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseLzwLiteral
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment LZW Stack Review) Tj ET';
    $lzwPayload = "Title,Status\nLZW Flate Attachment,Ready\n";
    $lzwCompressed = gzcompress($lzwPayload);
    if (!is_string($lzwCompressed)) {
        throw new RuntimeException('Unable to compress focused attachment LZW stack payload.');
    }

    $lzwEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseLzwLiteral($lzwCompressed);
    $surplusPayload = "Title,Status\nLZW Surplus Attachment,Blocked\n";
    $surplusCompressed = gzcompress($surplusPayload);
    if (!is_string($surplusCompressed)) {
        throw new RuntimeException('Unable to compress focused attachment LZW surplus payload.');
    }

    $surplusEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseLzwLiteral(
        $surplusCompressed,
        true,
        'BT /F1 12 Tf 72 680 Td (LZW attachment surplus bytes) Tj ET'
    );

    return [
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(lzw-flate.csv) 10 0 R (lzw-surplus.csv) 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (lzw-flate.csv) /Desc (LZW Flate attachment stack) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /LZWDecode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Params << /Size " . strlen($lzwPayload) . " /CheckSum <" . md5($lzwPayload) . "> >> /Length " . strlen($lzwEncoded) . " >>\nstream\n{$lzwEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (lzw-surplus.csv) /Desc (LZW surplus attachment stack) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /LZWDecode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Params << /Size " . strlen($surplusPayload) . " /CheckSum <" . md5($surplusPayload) . "> >> /Length " . strlen($surplusEncoded) . " >>\nstream\n{$surplusEncoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
        'payload' => $lzwPayload,
        'excluded_payload' => $surplusPayload,
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseShortLengthPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Short Length Stack Review) Tj ET';
    $payload = "Title,Status\nShort Length Attachment,Ready\n";
    $compressed = gzcompress($payload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused attachment short-length stack payload.');
    }
    $encoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($compressed);

    $surplusPayload = "Title,Status\nShort Length Surplus Attachment,Blocked\n";
    $surplusCompressed = gzcompress($surplusPayload);
    if (!is_string($surplusCompressed)) {
        throw new RuntimeException('Unable to compress focused attachment short-length surplus payload.');
    }
    $surplusCleanEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($surplusCompressed);
    $surplusEncoded = $surplusCleanEncoded
        . 'BT /F1 12 Tf 72 680 Td (short length attachment surplus bytes) Tj ET';

    return [
        'payload' => $payload,
        'excluded_payload' => $surplusPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(short-length-stack.csv) 10 0 R (short-length-surplus.csv) 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (short-length-stack.csv) /Desc (Short declared attachment stream length) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($payload) . " /CheckSum <" . md5($payload) . "> >> /Length " . max(0, strlen($encoded) - 7) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (short-length-surplus.csv) /Desc (Short declared surplus attachment stream length) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($surplusPayload) . " /CheckSum <" . md5($surplusPayload) . "> >> /Length " . max(0, strlen($surplusCleanEncoded) - 7) . " >>\nstream\n{$surplusEncoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseExtraDecodeParmsPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): string {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Stack Review) Tj ET';
    $ambiguousPayload = "Title,Status\nExtra DecodeParms Attachment Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Attachment After DecodeParms,Ready\n";
    $ambiguousEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($ambiguousPayload));
    $validEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($validPayload));

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(extra-decodeparms.csv) 10 0 R (valid-after-decodeparms.csv) 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (extra-decodeparms.csv) /Desc (Extra DecodeParms attachment leak) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null << /Predictor 1 >> ] /Params << /Size " . strlen($ambiguousPayload) . " /CheckSum <" . md5($ambiguousPayload) . "> >> /Length " . strlen($ambiguousEncoded) . " >>\nstream\n{$ambiguousEncoded}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (valid-after-decodeparms.csv) /Desc (Valid attachment after extra DecodeParms) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
};

$attachmentStreamFilterStackBoundaryCurrentBaseDuplicateDecodeParmsParameterPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment DecodeParms Parameter Review) Tj ET';
    $duplicatePredictorPayload = "Title,Status\nDuplicate Predictor Parameter Attachment Leak,Blocked\n";
    $duplicateCryptPayload = "Title,Status\nDuplicate Crypt Name Attachment Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Attachment After Parameter Duplicates,Ready\n";

    $duplicatePredictorEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(
        gzcompress($duplicatePredictorPayload)
    );
    $duplicateCryptEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($duplicateCryptPayload));
    $validEncoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85(gzcompress($validPayload));

    return [
        'duplicate_predictor_payload' => $duplicatePredictorPayload,
        'duplicate_crypt_payload' => $duplicateCryptPayload,
        'valid_payload' => $validPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(duplicate-predictor-parameter.csv) 10 0 R (duplicate-crypt-name.csv) 12 0 R (valid-after-parameter-duplicates.csv) 14 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (duplicate-predictor-parameter.csv) /Desc (Duplicate predictor parameter attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Predictor 1 /Columns 1 >> ] /Params << /Size " . strlen($duplicatePredictorPayload) . " /CheckSum <" . md5($duplicatePredictorPayload) . "> >> /Length " . strlen($duplicatePredictorEncoded) . " >>\nstream\n{$duplicatePredictorEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (duplicate-crypt-name.csv) /Desc (Duplicate Crypt Name attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /Crypt /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Name /PrivateCF /Name /Identity >> null null ] /Params << /Size " . strlen($duplicateCryptPayload) . " /CheckSum <" . md5($duplicateCryptPayload) . "> >> /Length " . strlen($duplicateCryptEncoded) . " >>\nstream\n{$duplicateCryptEncoded}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Filespec /F (valid-after-parameter-duplicates.csv) /Desc (Valid attachment after duplicate DecodeParms parameters) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseIndirectOperandPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Indirect Operand Review) Tj ET';
    $payload = "Title,Status\nIndirect Filter Operand Attachment,Ready\n";
    $predictedPayload = "\0" . $payload;
    $compressed = gzcompress($predictedPayload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused indirect filter operand fixture.');
    }
    $encoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($compressed);

    $cyclePayload = "Title,Status\nCyclic Filter Operand Attachment Leak,Blocked\n";

    return [
        'payload' => $payload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 50 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(indirect-filter-stack.csv) 10 0 R (cycle-filter-stack.csv) 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (indirect-filter-stack.csv) /Desc (Indirect filter operand attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ 20 0 R 21 0 R ] /DecodeParms 30 0 R /Params << /Size " . strlen($payload) . " /CheckSum <" . md5($payload) . "> >> /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (cycle-filter-stack.csv) /Desc (Cyclic filter operand attachment stack) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 40 0 R /Params << /Size " . strlen($cyclePayload) . " /CheckSum <" . md5($cyclePayload) . "> >> /Length " . strlen($cyclePayload) . " >>\nstream\n{$cyclePayload}\nendstream\nendobj\n"
            . "20 0 obj\n22 0 R\nendobj\n"
            . "21 0 obj\n23 0 R\nendobj\n"
            . "22 0 obj\n/ASCII85Decode\nendobj\n"
            . "23 0 obj\n/FlateDecode\nendobj\n"
            . "30 0 obj\n31 0 R\nendobj\n"
            . "31 0 obj\n[ null 32 0 R ]\nendobj\n"
            . "32 0 obj\n33 0 R\nendobj\n"
            . "33 0 obj\n<< /Predictor 12 /Columns " . strlen($payload) . " >>\nendobj\n"
            . "40 0 obj\n41 0 R\nendobj\n"
            . "41 0 obj\n40 0 R\nendobj\n"
            . "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$attachmentStreamFilterStackBoundaryCurrentBaseIndirectLengthPdf = static function () use (
    $attachmentStreamFilterStackBoundaryCurrentBaseAscii85
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Indirect Length Review) Tj ET';
    $payload = "Title,Status\nIndirect Length Attachment,Ready\n";
    $compressed = gzcompress($payload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused attachment indirect length fixture.');
    }

    $encoded = $attachmentStreamFilterStackBoundaryCurrentBaseAscii85($compressed);
    $tail = 'BT /F1 12 Tf 72 680 Td (Indirect length attachment fake tail) Tj ET';

    return [
        'payload' => $payload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(indirect-length-stack.csv) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (indirect-length-stack.csv) /Desc (Indirect Length attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($payload) . " /CheckSum <" . md5($payload) . "> >> /Length 40 % split attachment stream length reference\n 0 R >>\nstream\n{$encoded}{$tail}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "40 0 obj\n" . strlen($encoded) . "\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

return [
    'treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBasePdf): void {
        $pdf = $attachmentStreamFilterStackBoundaryCurrentBasePdf();
        $payload = "Title,Status\nIdentity Crypt Attachment,Ready\n";
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['identity-stack.csv'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('embedded-files-name-tree', $attachment['source'] ?? null);
        $t->same('identity-stack.csv', $attachment['filename'] ?? null);
        $t->same('Identity Crypt attachment stack', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same('text/csv', $attachment['content_type'] ?? null);
        $t->same(['Crypt', 'ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['declared_size'] ?? null);
        $t->same(true, $attachment['declared_size_matches'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['checksum_hex'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'private-stack.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Private Crypt Leak'));

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $t->same('identity-stack.csv', $files[0]['filename'] ?? null);
        $t->same('Identity Crypt attachment stack', $files[0]['description'] ?? null);
        $t->same(['Crypt', 'ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'private-stack.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Private Crypt Leak'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->true(str_contains($plainText, 'Visible Identity Attachment Review'));
        $t->true(!str_contains($plainText, 'Identity Crypt Attachment'));
        $t->true(!str_contains($plainText, 'Private Crypt Leak'));
    },
    'accepts attachment stream-filter EOD comments that end at the captured stream boundary' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseEodCommentPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseEodCommentPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['eod-comment-stack.csv'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('embedded-files-name-tree', $attachment['source'] ?? null);
        $t->same('eod-comment-stack.csv', $attachment['filename'] ?? null);
        $t->same('EOD comment attachment stack', $attachment['description'] ?? null);
        $t->same('Data', $attachment['relationship'] ?? null);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['declared_size'] ?? null);
        $t->same(true, $attachment['declared_size_matches'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['checksum_hex'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('eod-comment-stack.csv', $files[0]['filename'] ?? null);
        $t->same('EOD comment attachment stack', $files[0]['description'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment EOD Comment Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'attachment filter comment'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'attachment filter comment'));
        $t->true(!str_contains($plainText, 'EOD Comment Attachment'));
        $t->true(!str_contains($plainText, 'attachment filter comment'));
        $t->true(!str_contains($plainText, 'ASCII85Decode'));
        $t->true(!str_contains($plainText, 'FlateDecode'));
    },
    'rejects dictionary-valued attachment Filter operands before summary or payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseDictionaryFilterPdf): void {
        $pdf = $attachmentStreamFilterStackBoundaryCurrentBaseDictionaryFilterPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(0, $summary['attachment_count']);
        $t->same(0, $summary['total_bytes']);
        $t->same([], $summary['filenames']);
        $t->same([], $summary['attachments']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same([], $files);
        $t->same('Visible Attachment Malformed Filter Review', $plainText);
        $t->same(['Visible Attachment Malformed Filter Review'], (new PdfTextExtractor())->extractTextLines($pdf));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'dict-filter.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Dictionary Filter Attachment Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'dict-filter.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Dictionary Filter Attachment Leak'));
        $t->true(!str_contains($plainText, 'Dictionary Filter Attachment Leak'));
        $t->true(!str_contains($plainText, 'Malformed dictionary filter attachment'));
    },
    'rejects duplicate attachment stream Filter and DecodeParms declarations before payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseDuplicateStreamKeyPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseDuplicateStreamKeyPdf();
        $pdf = $fixture['pdf'];
        $validPayload = $fixture['valid_payload'];
        $duplicateFilterPayload = $fixture['duplicate_filter_payload'];
        $duplicateDecodeParmsPayload = $fixture['duplicate_decodeparms_payload'];
        $checksum = md5($validPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-after-duplicates.csv'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('valid-after-duplicates.csv', $attachment['filename'] ?? null);
        $t->same('Valid attachment after duplicate stream keys', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('valid-after-duplicates.csv', $files[0]['filename'] ?? null);
        $t->same($validPayload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Duplicate Stream Key Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'duplicate-filter.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'duplicate-decodeparms.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $validPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $duplicateFilterPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $duplicateDecodeParmsPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'duplicate-filter.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'duplicate-decodeparms.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $duplicateFilterPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $duplicateDecodeParmsPayload));
        $t->true(!str_contains($plainText, 'Duplicate Filter Attachment Leak'));
        $t->true(!str_contains($plainText, 'Duplicate DecodeParms Attachment Leak'));
        $t->true(!str_contains($plainText, 'Valid Attachment After Duplicate Keys'));
    },
    'rejects extra attachment Filter operands after scalar reference or null slots' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseExtraFilterOperandPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseExtraFilterOperandPdf();
        $pdf = $fixture['pdf'];
        $referencePayload = $fixture['reference_payload'];
        $nullPayload = $fixture['null_payload'];
        $validPayload = $fixture['valid_payload'];
        $checksum = md5($validPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-after-extra-filter.csv'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('valid-after-extra-filter.csv', $attachment['filename'] ?? null);
        $t->same('Valid attachment after extra filter operand', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('valid-after-extra-filter.csv', $files[0]['filename'] ?? null);
        $t->same($validPayload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Extra Filter Operand Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'extra-filter-reference.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'extra-filter-null.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $validPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $referencePayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $nullPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'extra-filter-reference.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'extra-filter-null.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $referencePayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $nullPayload));
        $t->true(!str_contains($plainText, 'Extra Filter Reference Operand Leak'));
        $t->true(!str_contains($plainText, 'Extra Filter Null Operand Leak'));
        $t->true(!str_contains($plainText, 'Valid Attachment After Extra Filter Operand'));
    },
    'rejects non-PDF whitespace inside attachment filter stack data before payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseFilterWhitespacePdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseFilterWhitespacePdf();
        $pdf = $fixture['pdf'];
        $badPayload = $fixture['bad_payload'];
        $goodPayload = $fixture['good_payload'];
        $checksum = md5($goodPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['clean-stack.csv'], $summary['filenames']);
        $t->same(strlen($goodPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('clean-stack.csv', $attachment['filename'] ?? null);
        $t->same('Clean attachment stack', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($goodPayload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('clean-stack.csv', $files[0]['filename'] ?? null);
        $t->same($goodPayload, $files[0]['content'] ?? null);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Filter Whitespace Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'bad-whitespace.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $badPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'bad-whitespace.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $badPayload));
        $t->true(!str_contains($plainText, 'Vertical Tab Stack Attachment Leak'));
        $t->true(!str_contains($plainText, 'Clean Stack Attachment'));
    },
    'treats all-null attachment filter arrays as identity stacks before resolving stray DecodeParms' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseAllNullPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseAllNullPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['all-null-attachment.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('all-null-attachment.csv', $attachment['filename'] ?? null);
        $t->same('All-null attachment filter stack', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same([], $attachment['filters'] ?? []);
        $t->same(strlen($payload), $attachment['declared_size'] ?? null);
        $t->same(true, $attachment['declared_size_matches'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['checksum_hex'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('all-null-attachment.csv', $files[0]['filename'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same([], $files[0]['filters'] ?? []);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment All Null Stack Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'All Null Attachment DecodeParms Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'All Null Attachment DecodeParms Leak'));
        $t->true(!str_contains($plainText, 'All Null Attachment'));
        $t->true(!str_contains($plainText, 'DecodeParms'));
    },
    'decodes LZW attachment filter stacks while rejecting bytes after the LZW EOD code' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseLzwPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseLzwPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $excludedPayload = $fixture['excluded_payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['lzw-flate.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('lzw-flate.csv', $attachment['filename'] ?? null);
        $t->same('LZW Flate attachment stack', $attachment['description'] ?? null);
        $t->same(['LZWDecode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('lzw-flate.csv', $files[0]['filename'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same(['LZWDecode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment LZW Stack Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'lzw-surplus.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $excludedPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'lzw-surplus.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $excludedPayload));
        $t->true(!str_contains($plainText, 'LZW Flate Attachment'));
        $t->true(!str_contains($plainText, 'LZW Surplus Attachment'));
        $t->true(!str_contains($plainText, 'LZW attachment surplus bytes'));
    },
    'recovers short declared attachment lengths only when the complete filter stack reaches endstream' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseShortLengthPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseShortLengthPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $excludedPayload = $fixture['excluded_payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['short-length-stack.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('short-length-stack.csv', $attachment['filename'] ?? null);
        $t->same('Short declared attachment stream length', $attachment['description'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['declared_size'] ?? null);
        $t->same(true, $attachment['declared_size_matches'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['checksum_hex'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('short-length-stack.csv', $files[0]['filename'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Short Length Stack Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'short-length-surplus.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $excludedPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'short-length-surplus.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $excludedPayload));
        $t->true(!str_contains($plainText, 'Short Length Attachment'));
        $t->true(!str_contains($plainText, 'Short Length Surplus Attachment'));
        $t->true(!str_contains($plainText, 'short length attachment surplus bytes'));
    },
    'rejects extra non-null DecodeParms entries in attachment filter stacks before summary or payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseExtraDecodeParmsPdf): void {
        $pdf = $attachmentStreamFilterStackBoundaryCurrentBaseExtraDecodeParmsPdf();
        $validPayload = "Title,Status\nValid Attachment After DecodeParms,Ready\n";
        $checksum = md5($validPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-after-decodeparms.csv'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('valid-after-decodeparms.csv', $attachment['filename'] ?? null);
        $t->same('Valid attachment after extra DecodeParms', $attachment['description'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('valid-after-decodeparms.csv', $files[0]['filename'] ?? null);
        $t->same($validPayload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Stack Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'extra-decodeparms.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Extra DecodeParms Attachment Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'extra-decodeparms.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Extra DecodeParms Attachment Leak'));
        $t->true(!str_contains($plainText, 'Extra DecodeParms Attachment Leak'));
        $t->true(!str_contains($plainText, 'Valid Attachment After DecodeParms'));
        $t->true(!str_contains($plainText, 'DecodeParms'));
    },
    'rejects duplicate DecodeParms parameters in attachment filter stacks before summary or payload extraction' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseDuplicateDecodeParmsParameterPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseDuplicateDecodeParmsParameterPdf();
        $pdf = $fixture['pdf'];
        $duplicatePredictorPayload = $fixture['duplicate_predictor_payload'];
        $duplicateCryptPayload = $fixture['duplicate_crypt_payload'];
        $validPayload = $fixture['valid_payload'];
        $checksum = md5($validPayload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['valid-after-parameter-duplicates.csv'], $summary['filenames']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('valid-after-parameter-duplicates.csv', $attachment['filename'] ?? null);
        $t->same('Valid attachment after duplicate DecodeParms parameters', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($validPayload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('valid-after-parameter-duplicates.csv', $files[0]['filename'] ?? null);
        $t->same($validPayload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment DecodeParms Parameter Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'duplicate-predictor-parameter.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'duplicate-crypt-name.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $validPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $duplicatePredictorPayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $duplicateCryptPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'duplicate-predictor-parameter.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'duplicate-crypt-name.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $duplicatePredictorPayload));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $duplicateCryptPayload));
        $t->true(!str_contains($plainText, 'Duplicate Predictor Parameter Attachment Leak'));
        $t->true(!str_contains($plainText, 'Duplicate Crypt Name Attachment Leak'));
        $t->true(!str_contains($plainText, 'Valid Attachment After Parameter Duplicates'));
    },
    'resolves chained indirect Filter and DecodeParms operands while failing closed on filter cycles' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseIndirectOperandPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseIndirectOperandPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['indirect-filter-stack.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('indirect-filter-stack.csv', $attachment['filename'] ?? null);
        $t->same('Indirect filter operand attachment stack', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('indirect-filter-stack.csv', $files[0]['filename'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Indirect Operand Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'cycle-filter-stack.csv'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Cyclic Filter Operand Attachment Leak'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'cycle-filter-stack.csv'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Cyclic Filter Operand Attachment Leak'));
        $t->true(!str_contains($plainText, 'Indirect Filter Operand Attachment'));
        $t->true(!str_contains($plainText, 'Cyclic Filter Operand Attachment Leak'));
    },
    'honors comment-split indirect Length operands before attachment stream-filter surplus checks' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterStackBoundaryCurrentBaseIndirectLengthPdf): void {
        $fixture = $attachmentStreamFilterStackBoundaryCurrentBaseIndirectLengthPdf();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $checksum = md5($payload);

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['indirect-length-stack.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('indirect-length-stack.csv', $attachment['filename'] ?? null);
        $t->same('Indirect Length attachment stack', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('indirect-length-stack.csv', $files[0]['filename'] ?? null);
        $t->same($payload, $files[0]['content'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $files[0]['filters'] ?? null);
        $t->same(strlen($payload), $files[0]['size'] ?? null);
        $t->same($checksum, $files[0]['computed_checksum'] ?? null);
        $t->same(true, $files[0]['checksum_matches'] ?? null);

        $t->same('Visible Attachment Indirect Length Review', $plainText);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'Indirect length attachment fake tail'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'Indirect length attachment fake tail'));
        $t->true(!str_contains($plainText, 'Indirect Length Attachment'));
        $t->true(!str_contains($plainText, 'Indirect length attachment fake tail'));
        $t->true(!str_contains($plainText, 'ASCII85Decode'));
        $t->true(!str_contains($plainText, 'FlateDecode'));
    },
];
