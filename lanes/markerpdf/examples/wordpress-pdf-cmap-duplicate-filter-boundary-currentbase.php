<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$pdfWithDuplicateCMapFilters = static function () use ($utf16beHex): array {
    $safeText = 'Duplicate Filter Safe Import';
    $leakingText = 'Duplicate Filter CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'WordPressDuplicateFilterBoundary-H';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress duplicate CMap filter smoke fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressDuplicateFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Filter /DCTDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$extractor = new PdfTextExtractor();
[$pdf, $safeText, $leakingText, $cMapName] = $pdfWithDuplicateCMapFilters();
$lines = $extractor->extractTextLines($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf:pdf-cmap-duplicate-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cmap-duplicate-filter-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'duplicate_filter_declarations_rejected' => ($review['duplicate_filter_declaration_count'] ?? null) === 1
        && ($review['decoded_cmap_count'] ?? null) === 0
        && ($entry['filter_operand_policy'] ?? null) === 'reject_duplicate_filter_declarations',
    'visible_text_uses_safe_fallback' => $lines === [$safeText],
    'visible_text_excludes_cmap_leak' => !str_contains(implode("\n", $lines), $leakingText)
        && !str_contains(implode("\n", $lines), $cMapName)
        && !str_contains(implode("\n", $lines), 'beginbfchar'),
    'filter_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'declared_filters_seen' => $entry['filters'] ?? [],
    'duplicate_filter_declaration_count' => $entry['duplicate_filter_declaration_count'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
