<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before DCT Mask Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After DCT Mask Import) Tj ET';
$softMaskJpeg = "\xff\xd8\xff\xe0JFIF\0wp soft mask jpeg\xff\xd9";
$explicitMaskJpeg = "\xff\xd8\xff\xe0JFIF\0wp explicit mask jpeg\xff\xd9";
$softMaskPayload = $softMaskJpeg . "\nBT /F1 12 Tf 72 700 Td (WordPress DCT soft mask leak) Tj ET\n";
$explicitMaskPayload = $explicitMaskJpeg . "\nBT /F1 12 Tf 72 690 Td (WordPress DCT explicit mask leak) Tj ET\n";
$primaryPayload = "\x00\x7f\xff";
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 6 0 R /Mask 7 0 R /Length " . strlen($primaryPayload) . " >>\nstream\n{$primaryPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($softMaskPayload) . " >>\nstream\n{$softMaskPayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /DCTDecode /Length " . strlen($explicitMaskPayload) . " >>\nstream\n{$explicitMaskPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$softMask = is_array($entry) ? ($entry['soft_mask_review'] ?? []) : [];
$explicitMask = is_array($entry) ? ($entry['mask_review'] ?? []) : [];

$expected = ['Before DCT Mask Import', 'After DCT Mask Import'];
$surplusExcluded = !str_contains($plainText, 'WordPress DCT soft mask leak')
    && !str_contains($plainText, 'WordPress DCT explicit mask leak')
    && !str_contains($plainText, 'JFIF');
$softMaskClipped = ($softMask['raw_length'] ?? null) === strlen($softMaskJpeg)
    && (($softMask['raw_length'] ?? 0) < strlen($softMaskPayload));
$explicitMaskClipped = ($explicitMask['raw_length'] ?? null) === strlen($explicitMaskJpeg)
    && (($explicitMask['raw_length'] ?? 0) < strlen($explicitMaskPayload));

if (
    $lines !== $expected
    || !$surplusExcluded
    || !$softMaskClipped
    || !$explicitMaskClipped
    || ($softMask['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($explicitMask['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($softMask['decoded_with_current_filters'] ?? true) !== false
    || ($explicitMask['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode nested mask boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-mask-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-mask-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only nested mask JPEG payloads',
    'paragraphs' => $lines,
    'soft_mask_declared_payload_length' => strlen($softMaskPayload),
    'soft_mask_jpeg_eoi_payload_length' => strlen($softMaskJpeg),
    'explicit_mask_declared_payload_length' => strlen($explicitMaskPayload),
    'explicit_mask_jpeg_eoi_payload_length' => strlen($explicitMaskJpeg),
    'soft_mask_post_eoi_surplus_clipped' => $softMaskClipped,
    'explicit_mask_post_eoi_surplus_clipped' => $explicitMaskClipped,
    'nested_mask_surplus_excluded_from_text' => $surplusExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
