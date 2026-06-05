<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$safePayload = '<wp-export><post id="safe-embedded-filter-stack"/></wp-export>';
$safeCompressed = gzcompress($safePayload);
if (!is_string($safeCompressed)) {
    throw new RuntimeException('Unable to compress safe embedded-file fixture.');
}

$unsafePayload = 'RAW_UNSUPPORTED_EMBEDDED_BYTES_SHOULD_NOT_COUNT';
$safeHex = strtoupper(bin2hex($safeCompressed)) . '>';
$unsafeHex = strtoupper(bin2hex($unsafePayload)) . '>';
$safeChecksum = strtoupper(hash('md5', $safePayload));
$unsafeChecksum = strtoupper(hash('md5', $unsafePayload));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "6 0 obj\n<< /Names [(safe.xml) 10 0 R (unsafe.bin) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (safe.xml) /Desc (Safe supported stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safeHex) . " >>\nstream\n{$safeHex}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (unsafe.bin) /Desc (Unsupported preview-only terminal filter) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /DCTDecode ] /Params << /Size " . strlen($unsafePayload) . " /CheckSum <{$unsafeChecksum}> >> /Length " . strlen($unsafeHex) . " >>\nstream\n{$unsafeHex}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encoded = json_encode($files, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode embedded-file smoke metadata.');
}

$safeFile = $files[0] ?? null;
$unsafeRejected = !str_contains($encoded, 'unsafe.bin')
    && !str_contains($encoded, 'UNSUPPORTED_EMBEDDED_BYTES')
    && !str_contains($encoded, strtolower($unsafeChecksum));
$safePayloadPreserved = is_array($safeFile)
    && ($safeFile['filename'] ?? null) === 'safe.xml'
    && ($safeFile['content'] ?? null) === $safePayload
    && ($safeFile['checksum_matches'] ?? false) === true;

if (count($files) !== 1 || !$safePayloadPreserved || !$unsafeRejected) {
    throw new RuntimeException('Unsupported EmbeddedFile terminal filters must be rejected before payload review.');
}

$metadata = [
    'native_boundary' => 'WordPress EmbeddedFiles stream-filter stack unsupported terminal filter fail-closed',
    'safe_file_count' => count($files),
    'safe_filters' => $safeFile['filters'] ?? [],
    'safe_payload_preserved' => $safePayloadPreserved,
    'unsupported_stack_rejected' => $unsafeRejected,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:embedded-file-filter-stack-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'safe.xml EmbeddedFile decoded while unsupported terminal stream filters were excluded from payload review',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
