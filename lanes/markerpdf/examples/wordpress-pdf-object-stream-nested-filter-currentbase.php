<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$asciiHexEncode = static fn (string $bytes): string => strtoupper(bin2hex($bytes)) . '>';

$safeFallbackContent = 'BT /F1 12 Tf 72 720 Td (Direct fallback survives nested filter review) Tj ET';
$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
];

$objectData = '';
$headerPairs = [];
$memberIndexes = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerPairs);
$objectStreamPlain = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPlain);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress nested-filter object-stream smoke fixture.');
}
$encodedObjectStream = $asciiHexEncode($compressedObjectStream);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter [ /ASCIIHexDecode [ /FlateDecode ] ] /Length ' . strlen($encodedObjectStream) . " >>\nstream\n{$encodedObjectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($safeFallbackContent) . " >>\nstream\n{$safeFallbackContent}\nendstream");

$xrefRows = ''
    . chr(2) . pack('N', 6) . chr($memberIndexes[1])
    . chr(2) . pack('N', 6) . chr($memberIndexes[2])
    . chr(2) . pack('N', 6) . chr($memberIndexes[3])
    . chr(2) . pack('N', 6) . chr($memberIndexes[4])
    . chr(1) . pack('N', $offsets[6]) . chr(0)
    . chr(1) . pack('N', $offsets[9]) . chr(0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress nested-filter xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "10 0 obj\n"
    . '<< /Type /XRef /Size 11 /Root 1 0 R /Index [1 4 6 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Direct fallback survives nested filter review']) {
    throw new RuntimeException('Expected malformed nested object-stream filter to fall back to the direct safe stream.');
}

echo '<!-- markerpdf-object-stream-nested-filter-currentbase-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-object-stream-parser',
    'native_boundary' => 'PDF object stream /Filter arrays must be flat; nested arrays fail closed before WordPress paragraph extraction',
    'nested_filter_object_stream_rejected' => $extractor->extractOutlineMetadata($pdf)['pages'] === 0,
    'direct_fallback_visible' => str_contains($plainText, 'Direct fallback survives nested filter review'),
    'xref_stream_payload_excluded' => !str_contains($plainText, 'ASCIIHexDecode') && !str_contains($plainText, 'FlateDecode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
