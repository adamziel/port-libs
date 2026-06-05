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
];
