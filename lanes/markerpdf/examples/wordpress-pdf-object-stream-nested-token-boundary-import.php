<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf 72 720 Td (Nested object stream page) Tj T* (Boundary parser survived) Tj ET';
$unreferencedNoise = 'BT /F1 12 Tf 72 720 Td (Unreferenced nested fallback noise) Tj ET';
$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US endobj token in catalog string) /Names << /Dests << /Nested [(99 0 obj) (endobj) (stream)] >> >> >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /PieceInfo << /WP << /Private << /Note (nested << >> [ ] endobj boundary) >> >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    9 => '<< /Type /Catalog /Pages 99 0 R /Note (unlisted compressed catalog) >>',
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
$objectStreamPlain = $header . "\n" . $objectData;

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
$addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . " /First {$first} /Length " . strlen($objectStreamPlain) . " >>\nstream\n{$objectStreamPlain}\nendstream");
$addObject(8, 0, "<< /Length " . strlen($unreferencedNoise) . " >>\nstream\n{$unreferencedNoise}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "7 0 obj\n"
    . "<< /Type /XRef /Size 10 /Root 1 0 R /Index [1 4] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-object-stream-nested-token-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 unfiltered object-stream member token-boundary parsing before Gutenberg paragraph rendering',
    'recovered_catalog_from_object_stream' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'excluded_unreferenced_fallback_stream' => !str_contains($plainText, 'Unreferenced nested fallback noise'),
    'excluded_unlisted_compressed_catalog' => !str_contains($plainText, 'unlisted compressed catalog'),
    'ignored_fake_obj_endobj_tokens_in_stream_payload' => !str_contains($plainText, '99 0 obj'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
