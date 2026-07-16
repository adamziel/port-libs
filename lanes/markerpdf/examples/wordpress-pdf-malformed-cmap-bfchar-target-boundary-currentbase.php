<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$safeText = 'AB';
$cMapName = 'WPBfcharMalformedTargetBoundary-H';
$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<0041> <0058FF>\n"
    . "<0042> <0042>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($toUnicode, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress malformed bfchar target CMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <00410042> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPBfcharMalformedTargetBoundary /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPBfcharMalformedTargetBoundary /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 66 1000] >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

if ($lines !== [$safeText]) {
    throw new RuntimeException('Malformed bfchar target CMap boundary did not preserve the safe WordPress import text.');
}

if (str_contains($plainText, 'X') || str_contains($plainText, "\u{FFFD}") || str_contains($plainText, $cMapName) || str_contains($plainText, 'beginbfchar')) {
    throw new RuntimeException('Malformed bfchar target CMap boundary leaked rejected ToUnicode program text.');
}

echo "<!-- wp:port-libs/markerpdf-cmap-boundary "
    . json_encode([
        'scenario' => 'filtered ToUnicode bfchar malformed scalar target boundary',
        'safe_text_preserved' => true,
        'malformed_bfchar_target_rejected' => true,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filters' => $entry['filters'] ?? [],
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
    ], JSON_UNESCAPED_SLASHES)
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
