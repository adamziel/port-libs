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
];
