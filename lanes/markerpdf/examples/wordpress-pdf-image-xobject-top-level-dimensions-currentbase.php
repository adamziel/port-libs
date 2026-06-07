<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress top-level image dimensions before import) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /Nested#20Dimension#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress top-level image dimensions after import) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Top Level Image Payload Noise) Tj ET';
$softMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Top Level Soft Mask Payload Noise) Tj ET';
$maskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Top Level Mask Payload Noise) Tj ET';
$alternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Top Level Alternate Payload Noise) Tj ET';
$imageCompressed = gzcompress($imagePayload);
$softMaskCompressed = gzcompress($softMaskPayload);
$maskCompressed = gzcompress($maskPayload);
$alternateCompressed = gzcompress($alternatePayload);
if (
    !is_string($imageCompressed)
    || !is_string($softMaskCompressed)
    || !is_string($maskCompressed)
    || !is_string($alternateCompressed)
) {
    throw new RuntimeException('Unable to compress Image XObject top-level dimension smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Nested#20Dimension#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 99 /Height 88 /BitsPerComponent 1 /StructParent 44 /StructParents 45 >> /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /StructParent 7 /StructParents 8 /Filter /FlateDecode /SMask 6 0 R /Mask 7 0 R /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 80 /Height 70 /BitsPerComponent 2 >> /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 60 /Height 50 /BitsPerComponent 2 >> /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 40 /Height 30 /BitsPerComponent 2 >> /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCompressed) . " >>\nstream\n{$alternateCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$softMask = is_array($entry['soft_mask_review'] ?? null) ? $entry['soft_mask_review'] : [];
$mask = is_array($entry['mask_review'] ?? null) ? $entry['mask_review'] : [];
$alternate = is_array($entry['alternate_images'][0] ?? null) ? $entry['alternate_images'][0] : [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($entry['width'] ?? null) !== 2
    || ($entry['height'] ?? null) !== 1
    || ($entry['bits_per_component'] ?? null) !== 8
    || ($entry['struct_parent'] ?? null) !== 7
    || ($entry['struct_parents'] ?? null) !== 8
    || ($entry['image_dimension_boundary'] ?? null) !== null
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || ($softMask['width'] ?? null) !== 2
    || ($softMask['height'] ?? null) !== 1
    || ($softMask['bits_per_component'] ?? null) !== 8
    || ($softMask['decoded_sha256'] ?? null) !== hash('sha256', $softMaskPayload)
    || ($mask['width'] ?? null) !== 2
    || ($mask['height'] ?? null) !== 1
    || ($mask['bits_per_component'] ?? null) !== 1
    || ($mask['decoded_sha256'] ?? null) !== hash('sha256', $maskPayload)
    || ($alternate['width'] ?? null) !== 4
    || ($alternate['height'] ?? null) !== 2
    || ($alternate['bits_per_component'] ?? null) !== 8
    || ($alternate['decoded_sha256'] ?? null) !== hash('sha256', $alternatePayload)
    || str_contains($plainText, 'WordPress Top Level Image Payload Noise')
    || str_contains($plainText, 'WordPress Top Level Soft Mask Payload Noise')
    || str_contains($plainText, 'WordPress Top Level Mask Payload Noise')
    || str_contains($plainText, 'WordPress Top Level Alternate Payload Noise')
) {
    throw new RuntimeException('Image XObject top-level dimension smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-top-level-dimensions-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image receives top-level Image XObject dimensions; nested image dictionary decoys stay outside searchable text extraction and raster handoff metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'top_level_dimensions_preserved' => ($entry['width'] ?? null) === 2
        && ($entry['height'] ?? null) === 1
        && ($entry['bits_per_component'] ?? null) === 8,
    'nested_dimension_decoys_excluded' => ($entry['width'] ?? null) !== 99
        && ($softMask['width'] ?? null) !== 80
        && ($mask['width'] ?? null) !== 60
        && ($alternate['width'] ?? null) !== 40,
    'struct_parent_preserved' => ($entry['struct_parent'] ?? null) === 7,
    'soft_mask_top_level_dimensions_preserved' => ($softMask['width'] ?? null) === 2
        && ($softMask['height'] ?? null) === 1
        && ($softMask['bits_per_component'] ?? null) === 8,
    'image_mask_top_level_dimensions_preserved' => ($mask['width'] ?? null) === 2
        && ($mask['height'] ?? null) === 1
        && ($mask['bits_per_component'] ?? null) === 1,
    'alternate_top_level_dimensions_preserved' => ($alternate['width'] ?? null) === 4
        && ($alternate['height'] ?? null) === 2
        && ($alternate['bits_per_component'] ?? null) === 8,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Top Level Image Payload Noise')
        || str_contains($plainText, 'WordPress Top Level Soft Mask Payload Noise')
        || str_contains($plainText, 'WordPress Top Level Mask Payload Noise')
        || str_contains($plainText, 'WordPress Top Level Alternate Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-top-level-dimensions-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
