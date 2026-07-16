<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf 72 720 Td (Object stream length filter page) Tj T* (Recovered compressed resources) Tj ET';
$unreferencedNoise = 'BT /F1 12 Tf 72 720 Td (Unreferenced direct fallback noise) Tj ET';
$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    9 => '<< /Type /Catalog /Pages 99 0 R >>',
];

$objectData = '';
$headerPairs = [];
$memberIndexes = [];
$memberIndex = 0;
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = $memberIndex;
    $objectData .= $body . "\n";
    $memberIndex++;
}

$header = implode(' ', $headerPairs);
$first = strlen($header) + 1;
$compressedObjectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object stream smoke fixture.');
}

$xrefRows = '';
foreach ([1, 2, 3, 4] as $objectNumber) {
    $xrefRows .= chr(2) . chr(6) . chr($memberIndexes[$objectNumber]);
}
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /ObjStm /N 90 0 R /First 91 0 R /Filter 92 0 R /Length 93 0 R >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(8, 0, "<< /Length " . strlen($unreferencedNoise) . " >>\nstream\n{$unreferencedNoise}\nendstream");
$addObject(90, 0, '94 0 R');
$addObject(91, 0, '95 0 R');
$addObject(92, 0, '/FlateDecode');
$addObject(93, 0, (string) strlen($compressedObjectStream));
$addObject(94, 0, (string) count($members));
$addObject(95, 0, (string) $first);

$xrefOffset = strlen($pdf);
$pdf .= "7 0 obj\n"
    . "<< /Type /XRef /Size 96 /Root 1 0 R /Index [1 4] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-object-stream-length-filter-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 object-stream indirect /Length /Filter /N /First recovery before Gutenberg paragraph rendering',
    'recovered_catalog_from_object_stream' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'excluded_unreferenced_fallback_stream' => !str_contains($plainText, 'Unreferenced direct fallback noise'),
    'excluded_unlisted_compressed_catalog' => !str_contains($plainText, 'Pages 99'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
