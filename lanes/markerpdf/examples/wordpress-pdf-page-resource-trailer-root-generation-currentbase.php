<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfPagePropertyExtractor.php';

$content = 'BT /F1 12 Tf 72 720 Td (Stale trailer root resource text) Tj ET q /StaleRootForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td (Stale trailer root inherited form) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleRootForm 6 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 1 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$review = $propertyExtractor->extractPageReviewMetadata($pdf);
$staleResourceReviewExcluded = $boundary === [] && $review === [];
$staleVisibleTextExcluded = $plainText === ''
    && $textExtractor->extractTextLines($pdf) === []
    && $textExtractor->extractTextRuns($pdf) === [];

if (!$staleResourceReviewExcluded || !$staleVisibleTextExcluded) {
    throw new RuntimeException('Expected generation-mismatched trailer Root to block stale page-resource text and review metadata.');
}

echo '<!-- markerpdf-page-resource-trailer-root-generation ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'generation-exact trailer /Root selection before inherited page-resource text and review metadata',
    'generation_mismatched_trailer_root_blocks_resource_review' => $staleResourceReviewExcluded,
    'stale_catalog_resource_metadata_excluded' => $staleResourceReviewExcluded,
    'stale_font_resource_excluded' => $staleResourceReviewExcluded,
    'stale_xobject_resource_excluded' => $staleResourceReviewExcluded,
    'generation_mismatched_trailer_root_blocks_visible_text' => $staleVisibleTextExcluded,
    'stale_resource_text_excluded' => !str_contains($plainText, 'Stale trailer root resource text'),
    'stale_form_text_excluded' => !str_contains($plainText, 'Stale trailer root inherited form'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>PDF page-resource text and review metadata were blocked because the trailer Root generation did not resolve to the current catalog.</p>\n";
echo "<!-- /wp:paragraph -->\n";
