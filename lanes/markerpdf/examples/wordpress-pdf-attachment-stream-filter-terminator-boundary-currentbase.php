<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85 = static function (string $bytes, string $suffix = ''): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
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

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($digits, 0, $chunkLength + 1);
    }

    return $encoded . '~>' . $suffix;
};

$runLength = static function (string $bytes, string $suffix = ''): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $encoded . chr(128) . $suffix;
};

$asciiHex = static function (string $bytes, string $suffix = ''): string {
    return strtoupper(bin2hex($bytes)) . '>' . $suffix;
};

$visible = 'BT /F1 12 Tf 72 720 Td (Attachment terminator boundary review) Tj ET';
$sourcePayload = "Title,Status\nTerminator Boundary Source,Ready\n";
$supplementPayload = "Title,Status\nTerminator Boundary Supplement,Ready\n";
$commentAscii85Payload = "Title,Status\nTerminator Comment ASCII85,Ready\n";
$commentAsciiHexPayload = "Title,Status\nTerminator Comment ASCIIHex,Ready\n";
$commentRunLengthPayload = "Title,Status\nTerminator Comment RunLength,Ready\n";
$ascii85Payload = "Title,Status\nASCII85 Surplus Source,Blocked\n";
$runLengthPayload = "Title,Status\nRunLength Surplus Source,Blocked\n";
$flatePayload = "Title,Status\nFlate Surplus Source,Blocked\n";

$sourceCompressed = gzcompress($sourcePayload);
$commentAscii85Compressed = gzcompress($commentAscii85Payload);
$commentAsciiHexCompressed = gzcompress($commentAsciiHexPayload);
$ascii85Compressed = gzcompress($ascii85Payload);
$supplementCompressed = gzcompress($supplementPayload);
$flateCompressed = gzcompress($flatePayload);
if (
    !is_string($sourceCompressed)
    || !is_string($commentAscii85Compressed)
    || !is_string($commentAsciiHexCompressed)
    || !is_string($ascii85Compressed)
    || !is_string($supplementCompressed)
    || !is_string($flateCompressed)
) {
    throw new RuntimeException('Unable to compress stream-filter terminator attachment smoke fixture.');
}

$files = [
    [
        'name' => 'terminator-source.csv',
        'object' => 11,
        'relationship' => 'Source',
        'payload' => $sourcePayload,
        'filter' => '[ /ASCII85Decode /FlateDecode ]',
        'stream' => $ascii85($sourceCompressed, "\n "),
    ],
    [
        'name' => 'terminator-supplement.csv',
        'object' => 13,
        'relationship' => 'Data',
        'payload' => $supplementPayload,
        'filter' => '/FlateDecode',
        'stream' => $supplementCompressed,
    ],
    [
        'name' => 'terminator-comment-ascii85.csv',
        'object' => 23,
        'relationship' => 'Supplement',
        'payload' => $commentAscii85Payload,
        'filter' => '[ /ASCII85Decode /FlateDecode ]',
        'stream' => $ascii85($commentAscii85Compressed, "\n% comment-only filter tail\n \t"),
    ],
    [
        'name' => 'terminator-comment-asciihex.csv',
        'object' => 25,
        'relationship' => 'Source',
        'payload' => $commentAsciiHexPayload,
        'filter' => '[ /ASCIIHexDecode /FlateDecode ]',
        'stream' => $asciiHex($commentAsciiHexCompressed, "\r% hex terminator comment tail\r\n"),
    ],
    [
        'name' => 'terminator-comment-runlength.csv',
        'object' => 27,
        'relationship' => 'Data',
        'payload' => $commentRunLengthPayload,
        'filter' => '/RunLengthDecode',
        'stream' => $runLength($commentRunLengthPayload, "\n% runlength terminator comment tail\n"),
    ],
    [
        'name' => 'terminator-ascii85-decoy.csv',
        'object' => 15,
        'relationship' => 'Data',
        'payload' => $ascii85Payload,
        'filter' => '[ /ASCII85Decode /FlateDecode ]',
        'stream' => $ascii85($ascii85Compressed, 'BT /F1 12 Tf 72 680 Td (ASCII85 decoy surplus) Tj ET'),
    ],
    [
        'name' => 'terminator-runlength-decoy.csv',
        'object' => 17,
        'relationship' => 'Data',
        'payload' => $runLengthPayload,
        'filter' => '/RunLengthDecode',
        'stream' => $runLength($runLengthPayload, 'BT /F1 12 Tf 72 660 Td (RunLength decoy surplus) Tj ET'),
    ],
    [
        'name' => 'terminator-flate-decoy.csv',
        'object' => 19,
        'relationship' => 'Data',
        'payload' => $flatePayload,
        'filter' => '/FlateDecode',
        'stream' => $flateCompressed . 'BT /F1 12 Tf 72 640 Td (Flate decoy surplus) Tj ET',
    ],
];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n";

$names = [];
$objects = '';
foreach ($files as $index => $file) {
    $filespecObject = 10 + ($index * 2);
    $streamObject = $file['object'];
    $checksum = md5($file['payload']);
    $names[] = '(' . $file['name'] . ") {$filespecObject} 0 R";
    $objects .= "{$filespecObject} 0 obj\n"
        . "<< /Type /Filespec /F ({$file['name']}) /Desc ({$file['name']} WordPress review) /AFRelationship /{$file['relationship']} /EF << /F {$streamObject} 0 R >> >>\n"
        . "endobj\n"
        . "{$streamObject} 0 obj\n"
        . "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter {$file['filter']} /Params << /Size " . strlen($file['payload']) . " /CheckSum <{$checksum}> >> /Length " . strlen($file['stream']) . " >>\n"
        . "stream\n{$file['stream']}\nendstream\nendobj\n";
}

$pdf .= "6 0 obj\n<< /Names [" . implode(' ', $names) . "] >>\nendobj\n"
    . $objects
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachmentNames = $summary['filenames'] ?? [];
$expectedNames = [
    'terminator-source.csv',
    'terminator-supplement.csv',
    'terminator-comment-ascii85.csv',
    'terminator-comment-asciihex.csv',
    'terminator-comment-runlength.csv',
];
$expectedPayloads = [
    $sourcePayload,
    $supplementPayload,
    $commentAscii85Payload,
    $commentAsciiHexPayload,
    $commentRunLengthPayload,
];

$smoke = [
    'native_boundary' => 'EmbeddedFiles filter-stack terminators must consume only whitespace before WordPress attachment review',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'valid_attachments_selected' => $attachmentNames === $expectedNames,
    'comment_tail_attachments_selected' => array_slice($attachmentNames, 2, 3) === array_slice($expectedNames, 2, 3),
    'embedded_payloads_available_to_review' => array_column($embedded, 'content') === $expectedPayloads,
    'ascii85_surplus_attachment_excluded' => !str_contains($summaryJson, 'terminator-ascii85-decoy.csv')
        && !str_contains($embeddedJson, $ascii85Payload),
    'runlength_surplus_attachment_excluded' => !str_contains($summaryJson, 'terminator-runlength-decoy.csv')
        && !str_contains($embeddedJson, $runLengthPayload),
    'flate_surplus_attachment_excluded' => !str_contains($summaryJson, 'terminator-flate-decoy.csv')
        && !str_contains($embeddedJson, $flatePayload),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $sourcePayload)
        && !str_contains($summaryJson, $supplementPayload)
        && !str_contains($summaryJson, $commentAscii85Payload)
        && !str_contains($summaryJson, $commentAsciiHexPayload)
        && !str_contains($summaryJson, $commentRunLengthPayload),
    'visible_text_kept_clean' => str_contains($plainText, 'Attachment terminator boundary review')
        && !str_contains($plainText, 'decoy surplus')
        && !str_contains($plainText, 'comment-only filter tail')
        && !str_contains($plainText, 'terminator comment tail'),
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

foreach ([
    'valid_attachments_selected',
    'comment_tail_attachments_selected',
    'embedded_payloads_available_to_review',
    'ascii85_surplus_attachment_excluded',
    'runlength_surplus_attachment_excluded',
    'flate_surplus_attachment_excluded',
    'payload_bytes_omitted_from_summary',
    'visible_text_kept_clean',
] as $flag) {
    if (($smoke[$flag] ?? false) !== true) {
        throw new RuntimeException('Attachment stream-filter terminator boundary smoke failed: ' . $flag);
    }
}

echo '<!-- markerpdf-attachment-stream-filter-terminator-boundary-currentbase-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach (explode("\n", trim($plainText)) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] ?? [] as $attachment) {
    echo '<li data-marker-attachment-checksum="'
        . htmlspecialchars((string) ($attachment['checksum_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars(
            (string) ($attachment['filename'] ?? 'attachment')
            . ' - ' . (string) ($attachment['relationship'] ?? 'unassociated')
            . ', ' . (string) ($attachment['content_type'] ?? 'application/octet-stream')
            . ', ' . (string) ($attachment['byte_length'] ?? 0) . ' bytes',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
