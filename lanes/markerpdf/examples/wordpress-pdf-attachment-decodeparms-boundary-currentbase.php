<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$safePayload = "Title,Status\nDefault Predictor,Ready\n";
$safeCompressed = gzcompress($safePayload);
if (!is_string($safeCompressed)) {
    throw new RuntimeException('Unable to compress safe attachment DecodeParms smoke payload.');
}

$unsafePayload = 'RAW_ATTACHMENT_DECODEPARMS_SHOULD_NOT_COUNT';
$unsafeCompressed = gzcompress($unsafePayload);
if (!is_string($unsafeCompressed)) {
    throw new RuntimeException('Unable to compress unsafe attachment DecodeParms smoke payload.');
}

$safeHex = strtoupper(bin2hex($safeCompressed)) . '>';
$unsafeHex = strtoupper(bin2hex($unsafeCompressed)) . '>';
$safeChecksum = md5($safePayload);
$unsafeChecksum = md5($unsafePayload);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Names [(safe-default.csv) 4 0 R (unsafe-predictor.bin) 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (safe-default.csv) /Desc (Default DecodeParms attachment) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 1 >> ] /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safeHex) . " >>\n"
    . "stream\n{$safeHex}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Filespec /F (unsafe-predictor.bin) /Desc (Unsupported DecodeParms attachment) /AFRelationship /Data /EF << /F 7 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 12 /Columns 8 >> ] /Params << /Size " . strlen($unsafePayload) . " /CheckSum <{$unsafeChecksum}> >> /Length " . strlen($unsafeHex) . " >>\n"
    . "stream\n{$unsafeHex}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$attachment = $summary['attachments'][0] ?? null;
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
if (!is_array($attachment) || $summaryJson === false) {
    throw new RuntimeException('Expected attachment DecodeParms smoke summary row.');
}

if (
    ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'safe-default.csv'
    || ($attachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, 'unsafe-predictor.bin')
    || str_contains($summaryJson, 'RAW_ATTACHMENT_DECODEPARMS')
    || str_contains($summaryJson, $unsafeChecksum)
) {
    throw new RuntimeException('Expected unsupported attachment DecodeParms to fail closed before checksum review.');
}

$metadata = [
    'native_boundary' => 'WordPress attachment preflight DecodeParms fail-closed boundary',
    'attachment_count' => $summary['attachment_count'] ?? null,
    'total_bytes' => $summary['total_bytes'] ?? null,
    'filename' => $attachment['filename'] ?? null,
    'filters' => $attachment['filters'] ?? [],
    'safe_attachment_preserved' => ($attachment['filename'] ?? null) === 'safe-default.csv',
    'default_decodeparms_accepted' => ($attachment['checksum_matches'] ?? null) === true,
    'unsupported_decodeparms_rejected' => !str_contains($summaryJson, 'unsafe-predictor.bin'),
    'unsafe_checksum_excluded' => !str_contains($summaryJson, $unsafeChecksum),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

echo '<!-- markerpdf:attachment-decodeparms-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
echo "<!-- wp:file {\"href\":\"media/safe-default.csv\"} -->\n";
echo '<div class="wp-block-file"><a href="media/safe-default.csv">'
    . htmlspecialchars('safe-default.csv', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
