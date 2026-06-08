<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentStreamFilterFakeEndstreamBoundaryAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($chunkLength === 4 && $value === 0) {
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

    return $encoded;
};

$attachmentStreamFilterFakeEndstreamBoundaryZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$attachmentStreamFilterFakeEndstreamBoundaryFixture = static function () use (
    $attachmentStreamFilterFakeEndstreamBoundaryAscii85,
    $attachmentStreamFilterFakeEndstreamBoundaryZlibStored
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Fake Endstream Boundary) Tj ET';
    $payloadPrefix = "Title,Status\nAttachment Stack Before,";
    while ((7 + strlen($payloadPrefix)) % 4 !== 0) {
        $payloadPrefix .= ' ';
    }
    $fakeEncodedEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if (!is_string($fakeEncodedEndstreamBytes)) {
        throw new RuntimeException('Unable to build focused fake endstream bytes.');
    }

    $payload = $payloadPrefix . $fakeEncodedEndstreamBytes . "Attachment Stack After,Ready\n";
    $encoded = $attachmentStreamFilterFakeEndstreamBoundaryAscii85(
        $attachmentStreamFilterFakeEndstreamBoundaryZlibStored($payload)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused fixture no longer contains encoded fake endstream marker.');
    }

    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';
    $checksum = md5($payload);

    return [
        'payload' => $payload,
        'encoded' => $encoded,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(fake-endstream-stack.csv) 10 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (fake-endstream-stack.csv) /Desc (Fake endstream attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($encoded) . " >>\n"
            . "stream\n{$encoded}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

return [
    'uses declared attachment stream length before fake encoded endstream markers' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterFakeEndstreamBoundaryFixture): void {
        $fixture = $attachmentStreamFilterFakeEndstreamBoundaryFixture();
        $pdf = $fixture['pdf'];
        $payload = $fixture['payload'];
        $encoded = $fixture['encoded'];

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(str_contains($encoded, "\nendstream\n!"));
        $t->same(1, $summary['attachment_count']);
        $t->same(['fake-endstream-stack.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(1, count($summary['attachments']));

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('fake-endstream-stack.csv', $attachment['filename'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same(strlen($payload), $attachment['declared_size'] ?? null);
        $t->same(md5($payload), $attachment['computed_checksum_hex'] ?? null);
        $t->same(md5($payload), $attachment['checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(!str_contains($summaryJson, $payload));
        $t->true(!str_contains($summaryJson, 'Attachment Stack Before'));
        $t->true(!str_contains($summaryJson, 'Attachment Stack After'));

        $t->same(1, count($files));
        $file = $files[0] ?? [];
        $t->same('fake-endstream-stack.csv', $file['filename'] ?? null);
        $t->same($payload, $file['content'] ?? null);
        $t->same(strlen($payload), $file['size'] ?? null);
        $t->same(strlen($payload), $file['declared_size'] ?? null);
        $t->same(md5($payload), $file['computed_checksum'] ?? null);
        $t->same(md5($payload), $file['checksum'] ?? null);
        $t->same(true, $file['checksum_matches'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $file['filters'] ?? null);

        $t->true(str_contains($plainText, 'Visible Attachment Fake Endstream Boundary'));
        $t->true(!str_contains($plainText, 'Attachment Stack Before'));
        $t->true(!str_contains($plainText, 'Attachment Stack After'));
        $t->true(!str_contains($plainText, 'fake endstream attachment stack'));
        $t->true(!str_contains($plainText, 'ASCII85Decode'));
        $t->true(!str_contains($plainText, 'FlateDecode'));
    },
];
