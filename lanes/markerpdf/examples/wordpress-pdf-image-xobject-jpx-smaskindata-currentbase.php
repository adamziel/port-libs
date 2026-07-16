<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current JPX SMaskInData Intro) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Embedded#20Alpha Do Q\n"
    . "q 12 0 0 12 110 690 cm /Invalid#20Alpha Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current JPX SMaskInData Outro) Tj ET';
$embeddedPayload = "\xff\x4fBT /F1 12 Tf 72 720 Td (WordPress Embedded JPX Alpha Noise) Tj ET\xff\xd9";
$ignoredMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Ignored SMask Noise) Tj ET';
$invalidPayload = "\xff\x4fBT /F1 12 Tf 72 720 Td (WordPress Invalid JPX Alpha Noise) Tj ET\xff\xd9";
$activeMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Active External SMask Noise) Tj ET';
$ignoredMaskCompressed = gzcompress($ignoredMaskPayload);
$activeMaskCompressed = gzcompress($activeMaskPayload);
if (!is_string($ignoredMaskCompressed) || !is_string($activeMaskCompressed)) {
    throw new RuntimeException('Unable to compress JPX SMaskInData smoke mask payloads.');
}

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Embedded#20Alpha 5 0 R /Invalid#20Alpha 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /SMaskInData 1 /SMask 6 0 R /Mask [0 0 120 140 200 255] /Length " . strlen($embeddedPayload) . " >>\nstream\n{$embeddedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($ignoredMaskCompressed) . " >>\nstream\n{$ignoredMaskCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /SMaskInData 9 0 R /SMask 8 0 R /Length " . strlen($invalidPayload) . " >>\nstream\n{$invalidPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($activeMaskCompressed) . " >>\nstream\n{$activeMaskCompressed}\nendstream\nendobj\n"
    . "9 0 obj\n9\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$embedded = $entriesByName['Embedded Alpha'] ?? [];
$invalid = $entriesByName['Invalid Alpha'] ?? [];
if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($embedded['jpx_soft_mask_in_data']['uses_embedded_soft_mask'] ?? false) !== true
    || ($embedded['jpx_soft_mask_in_data']['encoded_soft_mask_values'] ?? false) !== true
    || ($embedded['jpx_soft_mask_in_data']['external_soft_mask_ignored'] ?? false) !== true
    || !array_key_exists('soft_mask_review', $embedded)
    || $embedded['soft_mask_review'] !== null
    || ($embedded['mask_review']['suppressed_by_soft_mask'] ?? false) !== true
    || ($invalid['jpx_soft_mask_in_data']['valid_value'] ?? true) !== false
    || ($invalid['jpx_soft_mask_in_data']['external_soft_mask_ignored'] ?? true) !== false
    || ($invalid['soft_mask_review']['decoded_sha256'] ?? null) !== hash('sha256', $activeMaskPayload)
    || str_contains($plainText, 'WordPress Embedded JPX Alpha Noise')
    || str_contains($plainText, 'WordPress Ignored SMask Noise')
    || str_contains($plainText, 'WordPress Invalid JPX Alpha Noise')
    || str_contains($plainText, 'WordPress Active External SMask Noise')
) {
    throw new RuntimeException('JPX SMaskInData Image XObject smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-jpx-smaskindata-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB handoff keeps JPX embedded alpha as image review metadata, not WordPress paragraph text',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'embedded_jpx_soft_mask_present' => $embedded['jpx_embedded_soft_mask_present'] ?? false,
    'embedded_smaskindata_value' => $embedded['jpx_soft_mask_in_data']['value'] ?? null,
    'embedded_external_smask_ignored' => $embedded['jpx_soft_mask_in_data']['external_soft_mask_ignored'] ?? false,
    'embedded_colorkey_suppressed' => $embedded['mask_review']['suppressed_by_soft_mask'] ?? false,
    'invalid_smaskindata_external_smask_used' => ($invalid['soft_mask_review']['decoded_sha256'] ?? null) === hash('sha256', $activeMaskPayload),
    'payload_in_visible_text' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-jpx-smaskindata-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
