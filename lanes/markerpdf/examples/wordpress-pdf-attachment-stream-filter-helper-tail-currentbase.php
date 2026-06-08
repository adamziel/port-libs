<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
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

$buildPdf = static function () use ($ascii85Encode): array {
    $visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Helper Tail Review) Tj ET';
    $multiNamePayload = "Title,Status\nMulti Name Helper Attachment Leak,Blocked\n";
    $arrayTailPayload = "Title,Status\nArray Tail Helper Attachment Leak,Blocked\n";
    $validPayload = "Title,Status\nValid Exact Helper Attachment,Ready\n";

    $multiNameEncoded = $ascii85Encode(gzcompress($multiNamePayload));
    $arrayTailEncoded = $ascii85Encode(gzcompress($arrayTailPayload));
    $validEncoded = $ascii85Encode(gzcompress($validPayload));

    return [
        'valid_payload' => $validPayload,
        'pdf' => "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(multi-name-helper.csv) 10 0 R (array-tail-helper.csv) 12 0 R (valid-exact-helper.csv) 14 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (multi-name-helper.csv) /Desc (Malformed multi-name filter helper attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 20 0 R /Params << /Size " . strlen($multiNamePayload) . " /CheckSum <" . md5($multiNamePayload) . "> >> /Length " . strlen($multiNameEncoded) . " >>\nstream\n{$multiNameEncoded}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /F (array-tail-helper.csv) /Desc (Malformed array-tail filter helper attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 22 0 R /Params << /Size " . strlen($arrayTailPayload) . " /CheckSum <" . md5($arrayTailPayload) . "> >> /Length " . strlen($arrayTailEncoded) . " >>\nstream\n{$arrayTailEncoded}\nendstream\nendobj\n"
            . "14 0 obj\n<< /Type /Filespec /F (valid-exact-helper.csv) /Desc (Valid exact indirect filter helper attachment) /AFRelationship /Source /EF << /F 15 0 R >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter 24 0 R /Params << /Size " . strlen($validPayload) . " /CheckSum <" . md5($validPayload) . "> >> /Length " . strlen($validEncoded) . " >>\nstream\n{$validEncoded}\nendstream\nendobj\n"
            . "20 0 obj\n/ASCII85Decode /FlateDecode\nendobj\n"
            . "22 0 obj\n[ /ASCII85Decode /FlateDecode ] /RunLengthDecode\nendobj\n"
            . "24 0 obj\n26 0 R\nendobj\n"
            . "26 0 obj\n[ /ASCII85Decode /FlateDecode ] % exact helper comment\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    ];
};

$fixture = $buildPdf();
$validPayload = $fixture['valid_payload'];
$summary = (new PdfAttachmentExtractor())->attachmentSummary($fixture['pdf']);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($fixture['pdf']);
$plainText = (new PdfTextExtractor())->extractPlainText($fixture['pdf']);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

if (!is_string($summaryJson) || !is_string($filesJson)) {
    throw new RuntimeException('Expected attachment helper-tail smoke JSON output.');
}

$attachment = $summary['attachments'][0] ?? [];
$importedFile = $files[0] ?? [];
$flags = [
    'malformed_helper_attachments_rejected' => ($summary['attachment_count'] ?? null) === 1
        && ($summary['filenames'] ?? []) === ['valid-exact-helper.csv']
        && !str_contains($summaryJson, 'multi-name-helper.csv')
        && !str_contains($summaryJson, 'array-tail-helper.csv'),
    'valid_exact_helper_attachment_imported' => ($attachment['filename'] ?? null) === 'valid-exact-helper.csv'
        && ($attachment['filters'] ?? null) === ['ASCII85Decode', 'FlateDecode']
        && ($attachment['checksum_matches'] ?? null) === true,
    'summary_payload_bytes_omitted' => is_array($attachment)
        && !array_key_exists('bytes', $attachment),
    'payload_extracted_only_for_valid_helper' => count($files) === 1
        && ($importedFile['filename'] ?? null) === 'valid-exact-helper.csv'
        && ($importedFile['content'] ?? null) === $validPayload,
    'malformed_payloads_excluded' => !str_contains($filesJson, 'multi-name-helper.csv')
        && !str_contains($filesJson, 'array-tail-helper.csv')
        && !str_contains($filesJson, 'Multi Name Helper Attachment Leak')
        && !str_contains($filesJson, 'Array Tail Helper Attachment Leak'),
    'visible_text_preserved' => $plainText === 'Visible Attachment Helper Tail Review',
    'native_no_model_scope' => ($summary['executes_python_or_models'] ?? null) === false,
    'native_no_external_pdf_tools_scope' => ($summary['executes_external_pdf_tools'] ?? null) === false,
];

foreach ($flags as $flag => $passed) {
    if ($passed !== true && $passed !== false) {
        throw new RuntimeException("Attachment helper-tail smoke flag {$flag} did not produce a boolean.");
    }

    if ($passed !== true) {
        throw new RuntimeException("Attachment helper-tail smoke failed: {$flag}");
    }
}

$result = [
    'scenario' => 'wordpress_pdf_attachment_stream_filter_helper_tail_currentbase',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'filenames' => $summary['filenames'] ?? [],
    'imported_payload_sha256' => hash('sha256', $validPayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'flags' => $flags,
    'self_test_passed' => true,
];
$resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
if (!is_string($resultJson)) {
    throw new RuntimeException('Unable to encode attachment helper-tail smoke result.');
}

echo '<!-- markerpdf:attachment-stream-filter-helper-tail-boundary '
    . htmlspecialchars($resultJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo '<p>Imported attachment: '
    . htmlspecialchars((string) ($attachment['filename'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n";
