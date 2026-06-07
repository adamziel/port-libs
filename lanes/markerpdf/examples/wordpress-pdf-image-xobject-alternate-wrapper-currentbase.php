<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$primaryPayload = 'WORDPRESS-PRIMARY-ALTERNATE-WRAPPER-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
$currentAlternatePayload = 'WORDPRESS-CURRENT-WRAPPED-ALTERNATE-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
$staleAlternatePayload = 'WORDPRESS-STALE-WRAPPED-ALTERNATE-IMAGE-BYTES-MUST-NOT-BE-SELECTED';

$primaryCompressed = gzcompress($primaryPayload);
$currentAlternateCompressed = gzcompress($currentAlternatePayload);
$staleAlternateCompressed = gzcompress($staleAlternatePayload);

$visibleContent = "BT /F1 12 Tf 72 720 Td (Before wrapped alternate image) Tj ET\n"
    . "q 16 0 0 8 72 690 cm /Hero#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After wrapped alternate image) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
    . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
    . "3 0 obj << /Type /Page /Parent 2 0 R /Resources << /XObject << /Hero#20Image 5 0 R >> /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 20 0 R >> endobj\n"
    . "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Alternates 8 0 R /Length " . strlen($primaryCompressed) . " >>\nstream\n{$primaryCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleAlternateCompressed) . " >>\nstream\n{$staleAlternateCompressed}\nendstream\nendobj\n"
    . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentAlternateCompressed) . " >>\nstream\n{$currentAlternateCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n[<< /Image 9 0 R /DefaultForPrinting 12 0 R >> << /Image 13 0 R /DefaultForPrinting false >>]\nendobj\n"
    . "9 0 obj\n6 1 R\nendobj\n"
    . "12 0 obj\ntrue\nendobj\n"
    . "13 0 obj\n13 0 R\nendobj\n"
    . "20 0 obj << /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer << /Root 1 0 R >>\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$alternate = $entry['alternate_images'][0] ?? [];

$expectedLines = ['Before wrapped alternate image', 'After wrapped alternate image'];
$currentAlternateHash = hash('sha256', $currentAlternatePayload);
$staleAlternateHash = hash('sha256', $staleAlternatePayload);
$encodedReview = json_encode($review, JSON_THROW_ON_ERROR);
$payloadsExcluded = !str_contains($plainText, $primaryPayload)
    && !str_contains($plainText, $currentAlternatePayload)
    && !str_contains($plainText, $staleAlternatePayload)
    && !str_contains($encodedReview, $primaryPayload)
    && !str_contains($encodedReview, $currentAlternatePayload)
    && !str_contains($encodedReview, $staleAlternatePayload);
$resolvedWrappedAlternate = ($alternate['object_number'] ?? null) === 6
    && ($alternate['object_generation'] ?? null) === 1
    && ($alternate['decoded_sha256'] ?? null) === $currentAlternateHash
    && ($alternate['default_for_printing'] ?? null) === true
    && !str_contains($encodedReview, $staleAlternateHash);

if (
    $lines !== $expectedLines
    || ($entry['alternate_image_count'] ?? null) !== 1
    || ($entry['alternates_review_only'] ?? false) !== true
    || ($alternate['filters'] ?? []) !== ['FlateDecode']
    || ($alternate['decoded_with_current_filters'] ?? false) !== true
    || ($alternate['native_raster_decode'] ?? false) !== true
    || !$payloadsExcluded
    || !$resolvedWrappedAlternate
) {
    throw new RuntimeException('Wrapped Image XObject Alternates boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-image-xobject-alternate-wrapper-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-image-xobject-alternate-wrapper-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image alternate image handoff; wrapped /Alternates /Image operands resolve before WordPress media review',
    'paragraphs' => $lines,
    'alternate_image_count' => $entry['alternate_image_count'] ?? null,
    'alternate_object' => $alternate['object_number'] ?? null,
    'alternate_generation' => $alternate['object_generation'] ?? null,
    'indirect_default_for_printing' => $alternate['default_for_printing'] ?? null,
    'current_alternate_hash_selected' => ($alternate['decoded_sha256'] ?? null) === $currentAlternateHash,
    'stale_alternate_hash_excluded' => !str_contains($encodedReview, $staleAlternateHash),
    'alternate_payload_excluded_from_text' => $payloadsExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
