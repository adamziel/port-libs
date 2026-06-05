<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image Do Operand Intro) Tj ET\n"
    . "q 16 0 0 8 72 690 cm 99 /Malformed#20Image Do Q\n"
    . "q 12 0 0 6 104 690 cm /Valid#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image Do Operand Outro) Tj ET';
$malformedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Do Image Payload Noise) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Do Image Payload Noise) Tj ET';
$malformedCompressed = gzcompress($malformedPayload);
$validCompressed = gzcompress($validPayload);
if (!is_string($malformedCompressed) || !is_string($validCompressed)) {
    throw new RuntimeException('Unable to compress image XObject Do operand smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Image 5 0 R /Valid#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedCompressed) . " >>\nstream\n{$malformedCompressed}\nendstream\nendobj\n"
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
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 1
    || ($entriesByName['Malformed Image']['invoked'] ?? true) !== false
    || ($entriesByName['Malformed Image']['invocation_count'] ?? null) !== 0
    || ($entriesByName['Valid Image']['invoked'] ?? false) !== true
    || ($entriesByName['Valid Image']['decoded_sha256'] ?? null) !== hash('sha256', $validPayload)
    || str_contains($plainText, 'WordPress Malformed Do Image Payload Noise')
    || str_contains($plainText, 'WordPress Valid Do Image Payload Noise')
) {
    throw new RuntimeException('Image XObject Do operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-do-operand-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Image XObject Do takes exactly one name operand',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'malformed_extra_operand_unpainted' => ($entriesByName['Malformed Image']['invoked'] ?? true) === false,
    'valid_sibling_image_painted' => ($entriesByName['Valid Image']['invoked'] ?? false) === true,
    'valid_sibling_bbox' => $entriesByName['Valid Image']['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Malformed Do Image Payload Noise')
        || str_contains($plainText, 'WordPress Valid Do Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-do-operand-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
