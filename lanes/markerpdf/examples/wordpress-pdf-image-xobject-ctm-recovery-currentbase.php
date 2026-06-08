<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image CTM Recovery Intro) Tj ET\n"
    . "q 18 0 0 /Bad#20Scale 9 72 cm 20 0 0 10 72 690 cm /Recovered#20Ctm#20Image Do Q\n"
    . "q 12 0 0 6 110 690 cm /Valid#20Sibling Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image CTM Recovery Outro) Tj ET';
$recoveredPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Recovered CTM Image Payload Noise) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Sibling CTM Image Payload Noise) Tj ET';
$recoveredCompressed = gzcompress($recoveredPayload);
$validCompressed = gzcompress($validPayload);
if (!is_string($recoveredCompressed) || !is_string($validCompressed)) {
    throw new RuntimeException('Unable to compress image XObject CTM recovery smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Recovered#20Ctm#20Image 5 0 R /Valid#20Sibling 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($recoveredCompressed) . " >>\nstream\n{$recoveredCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 0
    || ($entriesByName['Recovered Ctm Image']['invocation_matrices'][0] ?? null) !== [20.0, 0.0, 0.0, 10.0, 72.0, 690.0]
    || ($entriesByName['Recovered Ctm Image']['image_unit_bbox'] ?? null) !== [72.0, 690.0, 92.0, 700.0]
    || ($entriesByName['Recovered Ctm Image']['malformed_ctm_operand_count'] ?? null) !== 0
    || ($entriesByName['Recovered Ctm Image']['malformed_ctm_operand_review_only'] ?? true) !== false
    || ($entriesByName['Recovered Ctm Image']['decoded_sha256'] ?? null) !== hash('sha256', $recoveredPayload)
    || ($entriesByName['Valid Sibling']['invocation_matrices'][0] ?? null) !== [12.0, 0.0, 0.0, 6.0, 110.0, 690.0]
    || ($entriesByName['Valid Sibling']['decoded_sha256'] ?? null) !== hash('sha256', $validPayload)
    || ($entriesByName['Valid Sibling']['malformed_ctm_operand_count'] ?? null) !== 0
    || str_contains($plainText, 'WordPress Recovered CTM Image Payload Noise')
    || str_contains($plainText, 'WordPress Valid Sibling CTM Image Payload Noise')
) {
    throw new RuntimeException('Image XObject CTM recovery smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-ctm-recovery-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; malformed cm review metadata is scoped to the image placement it actually affects',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'recovered_ctm_matrix_applied' => ($entriesByName['Recovered Ctm Image']['invocation_matrices'][0] ?? null) === [20.0, 0.0, 0.0, 10.0, 72.0, 690.0],
    'recovered_ctm_bbox' => $entriesByName['Recovered Ctm Image']['image_unit_bbox'] ?? null,
    'stale_malformed_cm_review_cleared' => ($entriesByName['Recovered Ctm Image']['malformed_ctm_operand_count'] ?? null) === 0,
    'recovered_ctm_review_only' => $entriesByName['Recovered Ctm Image']['malformed_ctm_operand_review_only'] ?? true,
    'valid_sibling_image_painted' => ($entriesByName['Valid Sibling']['invoked'] ?? false) === true,
    'valid_sibling_malformed_cm_operand_count' => $entriesByName['Valid Sibling']['malformed_ctm_operand_count'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Recovered CTM Image Payload Noise')
        || str_contains($plainText, 'WordPress Valid Sibling CTM Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-ctm-recovery-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
