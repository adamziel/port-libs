<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$imageRow = 'raw EI BT /F1 12 Tf 72 680 Td (Inline Object Stream Filter Noise) Tj ET';
$compressedImage = gzcompress("\0" . $imageRow, 0);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build inline-image fixture.');
}

$content = "BT /F1 12 Tf 72 720 Td (Object Stream Filter Inline Before) Tj ET\n"
    . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F [ null /Fl ] '
    . '/DP [ null << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ] ID "
    . $compressedImage . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (Object Stream Filter Inline After) Tj ET';
$storedPrefix = "% Stored filtered content contains stream-owner decoys.\n"
    . "endstream\nendobj\n"
    . "44 0 obj\n<< /Producer (Fake stream owner) >>\nendobj\n";
$compressedContent = gzcompress($storedPrefix . $content, 0);
if (!is_string($compressedContent)) {
    throw new RuntimeException('Unable to build content stream fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
    5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    30 => '/FlateDecode',
    31 => '<< /Predictor 1 >>',
    32 => (string) strlen($compressedContent),
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
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to build object stream fixture.');
}

$addObject(4, 0, "<< /Filter 30 0 R /DecodeParms 31 0 R /Length 32 0 R >>\nstream\n{$compressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = '';
foreach ([1, 2, 3, 4, 5, 6, 30, 31, 32] as $objectNumber) {
    $xrefRows .= ($objectNumber === 4 || $objectNumber === 6)
        ? chr(1) . pack('N', $offsets[$objectNumber . ':0']) . chr(0)
        : chr(2) . pack('N', 6) . chr($memberIndexes[$objectNumber]);
}
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to build xref stream fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /Index [1 6 30 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-object-stream-inline-image-filter-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-selected object-stream helpers repair a filtered content stream before inline image payload exclusion',
    'object_stream_helpers_recovered' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'fake_endstream_owner_excluded' => !str_contains($plainText, 'Fake stream owner')
        && !str_contains($plainText, '44 0 obj'),
    'fake_ei_inside_compressed_inline_image' => str_contains($compressedImage, ' EI '),
    'inline_image_payload_excluded' => !str_contains($plainText, 'Inline Object Stream Filter Noise')
        && !str_contains($plainText, 'raw EI'),
    'visible_text_imported' => $lines === ['Object Stream Filter Inline Before', 'Object Stream Filter Inline After'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
