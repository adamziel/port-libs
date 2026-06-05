<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before nested CCITT masks) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After nested CCITT masks) Tj ET';
$basePayload = "\x00\x01\x02";
$softPayload = 'BT /F1 12 Tf 72 700 Td (Nested WordPress SMask CCITT Payload Noise) Tj ET';
$maskPayload = 'BT /F1 12 Tf 72 700 Td (Nested WordPress Mask CCITT Payload Noise) Tj ET';
$alternatePayload = 'BT /F1 12 Tf 72 700 Td (Nested WordPress Alternate CCITT Payload Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BaseImage 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 7 0 R /Mask 8 0 R /Alternates [<< /Image 9 0 R /DefaultForPrinting true >>] /Length " . strlen($basePayload) . " >>\nstream\n{$basePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock true >> /Length " . strlen($softPayload) . " >>\nstream\n{$softPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 8 /Rows 1 /EndOfBlock true >> /Decode [1 0] /Length " . strlen($maskPayload) . " >>\nstream\n{$maskPayload}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 12 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 12 /Rows 1 /EncodedByteAlign true /EndOfBlock true >> /Length " . strlen($alternatePayload) . " >>\nstream\n{$alternatePayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$softBoundary = $entry['soft_mask_review']['ccitt_fax_decode_boundary'] ?? [];
$maskBoundary = $entry['mask_review']['ccitt_fax_decode_boundary'] ?? [];
$alternateBoundary = $entry['alternate_images'][0]['ccitt_fax_decode_boundary'] ?? [];

if (
    ($softBoundary['effective_decode_parms']['k'] ?? null) !== -1
    || ($maskBoundary['effective_width'] ?? null) !== 8
    || ($alternateBoundary['effective_decode_parms']['encoded_byte_align'] ?? null) !== true
    || str_contains($plainText, 'Nested WordPress SMask CCITT Payload Noise')
    || str_contains($plainText, 'Nested WordPress Mask CCITT Payload Noise')
    || str_contains($plainText, 'Nested WordPress Alternate CCITT Payload Noise')
) {
    throw new RuntimeException('Nested CCITT image boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-nested-image-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image nested image handoff',
    'base_image_count' => $review['image_xobject_count'] ?? null,
    'soft_mask_preview_filters' => $entry['soft_mask_review']['preview_only_filters'] ?? [],
    'soft_mask_ccitt_k' => $softBoundary['effective_decode_parms']['k'] ?? null,
    'explicit_mask_preview_filters' => $entry['mask_review']['preview_only_filters'] ?? [],
    'explicit_mask_effective_width' => $maskBoundary['effective_width'] ?? null,
    'alternate_preview_filters' => $entry['alternate_images'][0]['preview_only_filters'] ?? [],
    'alternate_encoded_byte_align' => $alternateBoundary['effective_decode_parms']['encoded_byte_align'] ?? null,
    'nested_payload_in_visible_text' => false,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-nested-image-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
