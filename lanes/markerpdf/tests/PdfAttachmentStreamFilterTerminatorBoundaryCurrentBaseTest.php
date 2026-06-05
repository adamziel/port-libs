<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentStreamFilterTerminatorBoundaryAscii85 = static function (string $bytes, string $suffix = ''): string {
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

    return $encoded . '~>' . $suffix;
};

$attachmentStreamFilterTerminatorBoundaryAsciiHex = static function (string $bytes, string $suffix = ''): string {
    return strtoupper(bin2hex($bytes)) . '>' . $suffix;
};

$attachmentStreamFilterTerminatorBoundaryRunLength = static function (string $bytes, string $suffix = ''): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $encoded . chr(128) . $suffix;
};

$attachmentStreamFilterTerminatorBoundaryPdf = static function () use (
    $attachmentStreamFilterTerminatorBoundaryAscii85,
    $attachmentStreamFilterTerminatorBoundaryAsciiHex,
    $attachmentStreamFilterTerminatorBoundaryRunLength
): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Terminator Review) Tj ET';
    $boundedAscii85Payload = "Title,Status\nBounded ASCII85 Attachment,Ready\n";
    $boundedFlatePayload = "Title,Status\nBounded Flate Attachment,Ready\n";
    $ascii85SurplusPayload = "Title,Status\nASCII85 Surplus Attachment,Blocked\n";
    $asciiHexSurplusPayload = "Title,Status\nASCIIHex Surplus Attachment,Blocked\n";
    $runLengthSurplusPayload = "Title,Status\nRunLength Surplus Attachment,Blocked\n";
    $flateSurplusPayload = "Title,Status\nFlate Surplus Attachment,Blocked\n";

    $compressedBoundedAscii85 = gzcompress($boundedAscii85Payload);
    $compressedAscii85Surplus = gzcompress($ascii85SurplusPayload);
    $compressedAsciiHexSurplus = gzcompress($asciiHexSurplusPayload);
    $boundedFlateBytes = gzcompress($boundedFlatePayload);
    $flateSurplusBytes = gzcompress($flateSurplusPayload);
    if (
        !is_string($compressedBoundedAscii85)
        || !is_string($compressedAscii85Surplus)
        || !is_string($compressedAsciiHexSurplus)
        || !is_string($boundedFlateBytes)
        || !is_string($flateSurplusBytes)
    ) {
        throw new RuntimeException('Unable to compress attachment stream-filter terminator fixture.');
    }

    $files = [
        [
            'name' => 'bounded-ascii85.csv',
            'object' => 11,
            'payload' => $boundedAscii85Payload,
            'filter' => '[ /ASCII85Decode /FlateDecode ]',
            'stream' => $attachmentStreamFilterTerminatorBoundaryAscii85($compressedBoundedAscii85, "\n \t"),
            'relationship' => 'Source',
        ],
        [
            'name' => 'bounded-flate.csv',
            'object' => 13,
            'payload' => $boundedFlatePayload,
            'filter' => '/FlateDecode',
            'stream' => $boundedFlateBytes,
            'relationship' => 'Data',
        ],
        [
            'name' => 'ascii85-surplus.csv',
            'object' => 15,
            'payload' => $ascii85SurplusPayload,
            'filter' => '[ /ASCII85Decode /FlateDecode ]',
            'stream' => $attachmentStreamFilterTerminatorBoundaryAscii85($compressedAscii85Surplus, 'BT /F1 12 Tf 72 680 Td (ASCII85 attachment surplus bytes) Tj ET'),
            'relationship' => 'Data',
        ],
        [
            'name' => 'asciihex-surplus.csv',
            'object' => 17,
            'payload' => $asciiHexSurplusPayload,
            'filter' => '[ /ASCIIHexDecode /FlateDecode ]',
            'stream' => $attachmentStreamFilterTerminatorBoundaryAsciiHex($compressedAsciiHexSurplus, 'BT /F1 12 Tf 72 660 Td (ASCIIHex attachment surplus bytes) Tj ET'),
            'relationship' => 'Data',
        ],
        [
            'name' => 'runlength-surplus.csv',
            'object' => 19,
            'payload' => $runLengthSurplusPayload,
            'filter' => '/RunLengthDecode',
            'stream' => $attachmentStreamFilterTerminatorBoundaryRunLength($runLengthSurplusPayload, 'BT /F1 12 Tf 72 640 Td (RunLength attachment surplus bytes) Tj ET'),
            'relationship' => 'Data',
        ],
        [
            'name' => 'flate-surplus.csv',
            'object' => 21,
            'payload' => $flateSurplusPayload,
            'filter' => '/FlateDecode',
            'stream' => $flateSurplusBytes . 'BT /F1 12 Tf 72 620 Td (Flate attachment surplus bytes) Tj ET',
            'relationship' => 'Data',
        ],
    ];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n";

    $nameEntries = [];
    $objectBytes = '';
    foreach ($files as $index => $file) {
        $filespecObject = 10 + ($index * 2);
        $streamObject = $file['object'];
        $nameEntries[] = '(' . $file['name'] . ") {$filespecObject} 0 R";
        $checksum = md5($file['payload']);
        $objectBytes .= "{$filespecObject} 0 obj\n"
            . "<< /Type /Filespec /F ({$file['name']}) /Desc ({$file['name']} review) /AFRelationship /{$file['relationship']} /EF << /F {$streamObject} 0 R >> >>\n"
            . "endobj\n"
            . "{$streamObject} 0 obj\n"
            . "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter {$file['filter']} /Params << /Size " . strlen($file['payload']) . " /CheckSum <{$checksum}> >> /Length " . strlen($file['stream']) . " >>\n"
            . "stream\n{$file['stream']}\nendstream\nendobj\n";
    }

    $pdf .= "6 0 obj\n<< /Names [" . implode(' ', $nameEntries) . "] >>\nendobj\n"
        . $objectBytes
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [
        'pdf' => $pdf,
        'included_payloads' => [$boundedAscii85Payload, $boundedFlatePayload],
        'excluded_payloads' => [
            $ascii85SurplusPayload,
            $asciiHexSurplusPayload,
            $runLengthSurplusPayload,
            $flateSurplusPayload,
        ],
        'included_names' => ['bounded-ascii85.csv', 'bounded-flate.csv'],
        'excluded_names' => ['ascii85-surplus.csv', 'asciihex-surplus.csv', 'runlength-surplus.csv', 'flate-surplus.csv'],
    ];
};

return [
    'rejects attachment streams with non-whitespace bytes after filter terminators' => static function (
        TestRunner $t
    ) use ($attachmentStreamFilterTerminatorBoundaryPdf): void {
        $fixture = $attachmentStreamFilterTerminatorBoundaryPdf();
        $pdf = $fixture['pdf'];

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same($fixture['included_names'], $summary['filenames']);
        $t->same(
            strlen($fixture['included_payloads'][0]) + strlen($fixture['included_payloads'][1]),
            $summary['total_bytes']
        );
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(2, count($files));
        $t->same($fixture['included_names'], array_column($files, 'filename'));

        foreach ($fixture['included_payloads'] as $index => $payload) {
            $t->same($payload, $files[$index]['content'] ?? null);
            $t->same(strlen($payload), $summary['attachments'][$index]['byte_length'] ?? null);
            $t->same(strlen($payload), $files[$index]['size'] ?? null);
            $t->same(md5($payload), $summary['attachments'][$index]['computed_checksum_hex'] ?? null);
            $t->same(md5($payload), $files[$index]['computed_checksum'] ?? null);
            $t->same(true, $summary['attachments'][$index]['checksum_matches'] ?? null);
            $t->same(true, $files[$index]['checksum_matches'] ?? null);
            $t->same(false, array_key_exists('bytes', $summary['attachments'][$index]));
            $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        }

        foreach ($fixture['excluded_names'] as $name) {
            $t->true(!in_array($name, $summary['filenames'], true));
            $t->true(!in_array($name, array_column($files, 'filename'), true));
            $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $name));
            $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $name));
        }

        foreach ($fixture['excluded_payloads'] as $payload) {
            $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
            $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $payload));
            $t->true(!str_contains($plainText, trim($payload)));
        }

        $t->true(str_contains($plainText, 'Visible Attachment Terminator Review'));
        $t->true(!str_contains($plainText, 'attachment surplus bytes'));
    },
];
