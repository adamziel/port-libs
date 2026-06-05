<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale kid generation text) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation fallback label leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "3 1 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();

$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$labels = $textExtractor->extractPageLabels($pdf);
$pageCount = $textExtractor->extractOutlineMetadata($pdf)['pages'];
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$review = $propertyExtractor->extractPageReviewMetadata($pdf);

if ($lines !== [] || $plainText !== '' || $labels !== [] || $pageCount !== 0 || $boundary !== [] || $review !== []) {
    throw new RuntimeException('Expected stale page-tree Kids generation to block labels, visible text, and page resource review.');
}

echo '<!-- markerpdf-page-resource-kid-generation-label-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-kid-generation-label-boundary-currentbase',
    'native_boundary' => 'catalog /Kids generation selection is authoritative before page-label fallback and inherited resource review',
    'catalog_page_tree_blocks_stream_label_fallback' => $labels === [],
    'selected_page_count' => $pageCount,
    'visible_text_blocked' => $plainText === '',
    'page_resource_review_blocked' => $boundary === [] && $review === [],
    'current_generation_stream_excluded' => !str_contains($plainText, 'Current generation fallback label leak'),
    'stale_generation_stream_excluded' => !str_contains($plainText, 'Stale kid generation text'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>PDF page labels, visible text, and page-resource review were blocked because the catalog page tree selected a stale page generation.</p>\n";
echo "<!-- /wp:paragraph -->\n";
