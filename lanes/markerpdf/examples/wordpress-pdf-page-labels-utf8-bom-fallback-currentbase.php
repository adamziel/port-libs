<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contents = [
    10 => 'BT /F1 12 Tf 72 720 Td (UTF8 BOM fallback first page) Tj ET',
    11 => 'BT /F1 12 Tf 72 720 Td (UTF8 BOM fallback malformed page) Tj ET',
];
$validPrefixText = "R\u{00E9}sum\u{00E9} ";
$validPrefix = strtoupper(bin2hex("\xEF\xBB\xBF" . $validPrefixText));
$malformedPrefix = strtoupper(bin2hex("\xEF\xBB\xBF" . "\xC3" . 'Broken '));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 99 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Resources << /Font << /F1 8 0 R >> >> /MediaBox [0 0 612 792] /Contents 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

foreach ($contents as $objectNumber => $content) {
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
}

$pdf .= "20 0 obj\n<< /Nums [0 << /P <{$validPrefix}> /S /D /St 5 >> 1 << /P <{$malformedPrefix}> /S /D /St 9 >>] >>\nendobj\n"
    . "%%EOF\n";

$preview = new MarkerAppPreview();
$extractor = new PdfTextExtractor();
$summary = $preview->openPdfSummary($pdf);
$labels = array_column($summary['pages'], 'page_label');
$expectedLabels = [$validPrefixText . '5', '9'];
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$textExtractorLabels = $extractor->extractPageLabels($pdf);

if ($labels !== $expectedLabels) {
    throw new RuntimeException('Expected MarkerAppPreview fallback to decode valid UTF-8 BOM PageLabels and reject malformed UTF-8 BOM prefixes.');
}

if ($textExtractorLabels !== []) {
    throw new RuntimeException('Expected text-extractor PageLabels to stay unavailable when the selected catalog /Pages reference is missing.');
}

foreach (["\u{00EF}\u{00BB}\u{00BF}", 'Broken 9'] as $leakedLabel) {
    if (str_contains($encodedSummary, $leakedLabel)) {
        throw new RuntimeException('Expected WordPress preview metadata to exclude raw or malformed UTF-8 BOM PageLabels labels.');
    }
}

echo '<!-- markerpdf-page-labels-utf8-bom-fallback-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'marker_app preview fallback decodes PDF 2.0 UTF-8 BOM PageLabels prefixes and rejects malformed BOM prefixes',
    'text_extractor_page_labels_unavailable' => $textExtractorLabels === [],
    'preview_page_labels' => $labels,
    'valid_utf8_bom_prefix_decoded' => $labels[0] === $expectedLabels[0],
    'malformed_utf8_bom_prefix_rejected' => $labels[1] === '9',
    'raw_bom_mojibake_excluded' => !str_contains($encodedSummary, "\u{00EF}\u{00BB}\u{00BF}"),
    'malformed_prefix_text_excluded' => !str_contains($encodedSummary, 'Broken 9'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($summary['pages'] as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>Preview fallback page ' . $page['page_number'] . ': '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
