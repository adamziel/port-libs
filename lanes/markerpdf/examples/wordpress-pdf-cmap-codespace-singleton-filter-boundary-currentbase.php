<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$safeText = 'AB';
$cMapName = 'WPCodespaceSingletonFilterBoundary-H';
$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<00>\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($encodingCMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress codespace singleton CMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <00410042> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPCodespaceSingletonFilterBoundary /Encoding 6 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPCodespaceSingletonFilterBoundary /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 66 1000] >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$span = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

if ($lines !== [$safeText]) {
    throw new RuntimeException('Expected filtered CMap codespace singleton boundary to preserve Type0 fallback text.');
}
if (str_contains($plainText, "\0") || str_contains($plainText, $cMapName) || str_contains($plainText, 'begincodespacerange')) {
    throw new RuntimeException('Expected malformed codespace singleton and CMap program bytes to stay out of paragraphs.');
}
if (($entry['filters'] ?? null) !== ['FlateDecode'] || ($entry['decoded_with_current_operands'] ?? null) !== true) {
    throw new RuntimeException('Expected filtered Encoding CMap review metadata to remain decoded and current.');
}

echo '<!-- markerpdf-cmap-codespace-singleton-filter-boundary-currentbase-smoke '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'filtered Encoding CMap codespace singleton rows ignored before Type0 source-width fallback',
        'safe_text_preserved' => true,
        'nul_bytes_excluded' => true,
        'cmap_program_text_excluded' => true,
        'encoding_cmap_stream_count' => $review['encoding_cmap_stream_count'] ?? null,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filters' => $entry['filters'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
        'span_bbox' => $span['bbox'] ?? null,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
