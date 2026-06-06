<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current ExtGState SMask None Intro) Tj ET\n"
    . "q /Soft#20Mask#20State gs /No#20Soft#20Mask#20State gs 20 0 0 10 72 690 cm /Cleared#20Soft#20Image Do Q\n"
    . "q /Soft#20Mask#20State gs 12 0 0 6 120 690 cm /Masked#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current ExtGState SMask None Outro) Tj ET';
$clearedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Cleared ExtGState SMask None Image Noise) Tj ET';
$maskedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Masked ExtGState SMask Image Noise) Tj ET';
$clearedCompressed = gzcompress($clearedPayload);
$maskedCompressed = gzcompress($maskedPayload);
if (!is_string($clearedCompressed) || !is_string($maskedCompressed)) {
    throw new RuntimeException('Unable to compress ExtGState SMask None smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Soft#20Mask#20State 20 0 R /No#20Soft#20Mask#20State 21 0 R >> /XObject << /Cleared#20Soft#20Image 5 0 R /Masked#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($clearedCompressed) . " >>\nstream\n{$clearedCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($maskedCompressed) . " >>\nstream\n{$maskedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /ca 0.8 /BM /Screen /SMask 22 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Type /ExtGState /ca 0.65 /BM /Normal /SMask /None >>\nendobj\n"
    . "22 0 obj\n<< /Type /Mask /S /Luminosity /G 23 0 R /TR /Identity >>\nendobj\n"
    . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$cleared = $entriesByName['Cleared Soft Image'] ?? [];
$masked = $entriesByName['Masked Image'] ?? [];
$clearedState = $cleared['invocation_graphics_states'][0] ?? [];
$maskedState = $masked['invocation_graphics_states'][0] ?? [];
$clearedSoftMaskIsNull = array_key_exists('soft_mask', $clearedState) && $clearedState['soft_mask'] === null;

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || !$clearedSoftMaskIsNull
    || (($clearedState['ext_gstate_resources'] ?? []) !== ['Soft Mask State', 'No Soft Mask State'])
    || (($clearedState['nonstroking_alpha'] ?? null) !== 0.65)
    || (($maskedState['soft_mask']['type'] ?? null) !== 'graphics_state_soft_mask')
    || (($masked['decoded_sha256'] ?? null) !== hash('sha256', $maskedPayload))
    || str_contains($plainText, 'WordPress Cleared ExtGState SMask None Image Noise')
    || str_contains($plainText, 'WordPress Masked ExtGState SMask Image Noise')
) {
    throw new RuntimeException('ExtGState SMask None image XObject smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-extgstate-smask-none-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; graphics-state SMask None clears current soft mask before image paint',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'cleared_soft_mask_is_null' => $clearedSoftMaskIsNull,
    'cleared_extgstate_resources' => $clearedState['ext_gstate_resources'] ?? [],
    'cleared_nonstroking_alpha' => $clearedState['nonstroking_alpha'] ?? null,
    'masked_soft_mask_type' => $maskedState['soft_mask']['type'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Cleared ExtGState SMask None Image Noise')
        || str_contains($plainText, 'WordPress Masked ExtGState SMask Image Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-extgstate-smask-none-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
