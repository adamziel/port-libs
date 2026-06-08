<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress indirect name operand intro) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /Tailed#20Indirect#20Subtype#20Image Do Q\n"
    . "q 10 0 0 5 96 690 cm /Tailed#20Indirect#20Type#20Image Do Q\n"
    . "q 8 0 0 4 120 690 cm /Valid#20Indirect#20Name#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress indirect name operand outro) Tj ET';
$tailedSubtypePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Tailed Indirect Subtype Image Payload Noise) Tj ET';
$tailedTypePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Tailed Indirect Type Image Payload Noise) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Indirect Name Image Payload Noise) Tj ET';
$tailedSubtypeCompressed = gzcompress($tailedSubtypePayload);
$tailedTypeCompressed = gzcompress($tailedTypePayload);
$validCompressed = gzcompress($validPayload);
if (
    !is_string($tailedSubtypeCompressed)
    || !is_string($tailedTypeCompressed)
    || !is_string($validCompressed)
) {
    throw new RuntimeException('Unable to compress indirect Image XObject name operand smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Tailed#20Indirect#20Subtype#20Image 5 0 R /Tailed#20Indirect#20Type#20Image 6 0 R /Valid#20Indirect#20Name#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype 20 0 R /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tailedSubtypeCompressed) . " >>\nstream\n{$tailedSubtypeCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type 21 0 R /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tailedTypeCompressed) . " >>\nstream\n{$tailedTypeCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type 22 0 R /Subtype 23 0 R /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "20 0 obj\n/Image 99 0 R\nendobj\n"
    . "21 0 obj\n/XObject 99 0 R\nendobj\n"
    . "22 0 obj\n/XObject\nendobj\n"
    . "23 0 obj\n/Image\nendobj\n"
    . "99 0 obj\n<< /S /JavaScript /JS (decoy) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}
$valid = $entriesByName['Valid Indirect Name Image'] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || isset($entriesByName['Tailed Indirect Subtype Image'])
    || isset($entriesByName['Tailed Indirect Type Image'])
    || ($valid['decoded_sha256'] ?? null) !== hash('sha256', $validPayload)
    || str_contains($plainText, 'WordPress Tailed Indirect Subtype Image Payload Noise')
    || str_contains($plainText, 'WordPress Tailed Indirect Type Image Payload Noise')
    || str_contains($plainText, 'WordPress Valid Indirect Name Image Payload Noise')
) {
    throw new RuntimeException('Indirect Image XObject name operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-indirect-name-operand-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; indirect Image XObject Type/Subtype name operands must be standalone names',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'indirect_subtype_tail_rejected' => !isset($entriesByName['Tailed Indirect Subtype Image']),
    'indirect_type_tail_rejected' => !isset($entriesByName['Tailed Indirect Type Image']),
    'valid_indirect_name_image_reviewed' => ($valid['decoded_sha256'] ?? null) === hash('sha256', $validPayload),
    'valid_image_bbox' => $valid['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Tailed Indirect Subtype Image Payload Noise')
        || str_contains($plainText, 'WordPress Tailed Indirect Type Image Payload Noise')
        || str_contains($plainText, 'WordPress Valid Indirect Name Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-indirect-name-operand-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
