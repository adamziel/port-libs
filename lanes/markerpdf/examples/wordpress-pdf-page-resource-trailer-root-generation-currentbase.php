<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;

require_once __DIR__ . '/../src/PdfPagePropertyExtractor.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleRootForm 6 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 1 R >>\n%%EOF";

$extractor = new PdfPagePropertyExtractor();
$boundary = $extractor->extractPageBoundaryMetadata($pdf);
$review = $extractor->extractPageReviewMetadata($pdf);
$staleResourceReviewExcluded = $boundary === [] && $review === [];

if (!$staleResourceReviewExcluded) {
    throw new RuntimeException('Expected generation-mismatched trailer Root to block stale page-resource review metadata.');
}

echo '<!-- markerpdf-page-resource-trailer-root-generation ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'generation-exact trailer /Root selection before inherited page-resource review metadata',
    'generation_mismatched_trailer_root_blocks_resource_review' => $staleResourceReviewExcluded,
    'stale_catalog_resource_metadata_excluded' => $staleResourceReviewExcluded,
    'stale_font_resource_excluded' => $staleResourceReviewExcluded,
    'stale_xobject_resource_excluded' => $staleResourceReviewExcluded,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>PDF page-resource review metadata was blocked because the trailer Root generation did not resolve to the current catalog.</p>\n";
echo "<!-- /wp:paragraph -->\n";
