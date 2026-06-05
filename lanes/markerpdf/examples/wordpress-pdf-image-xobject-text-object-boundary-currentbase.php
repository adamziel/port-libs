<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Text Object Image Boundary Intro) Tj /Fake#20Text#20Image Do ( Still searchable text) Tj ET\n"
    . "q 14 0 0 7 104 690 cm /Painted#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Text Object Image Boundary Outro) Tj ET';
$fakePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Fake Text Object Image Payload Noise) Tj ET';
$paintedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Painted Image Payload Noise) Tj ET';
$fakeCompressed = gzcompress($fakePayload);
$paintedCompressed = gzcompress($paintedPayload);
if (!is_string($fakeCompressed) || !is_string($paintedCompressed)) {
    throw new RuntimeException('Unable to compress text-object image boundary smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fake#20Text#20Image 5 0 R /Painted#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($fakeCompressed) . " >>\nstream\n{$fakeCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($paintedCompressed) . " >>\nstream\n{$paintedCompressed}\nendstream\nendobj\n"
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
    || ($entriesByName['Fake Text Image']['invoked'] ?? true) !== false
    || ($entriesByName['Fake Text Image']['invocation_count'] ?? null) !== 0
    || ($entriesByName['Fake Text Image']['decoded_sha256'] ?? null) !== hash('sha256', $fakePayload)
    || ($entriesByName['Painted Image']['invoked'] ?? false) !== true
    || ($entriesByName['Painted Image']['image_unit_bbox'] ?? null) !== [104.0, 690.0, 118.0, 697.0]
    || ($entriesByName['Painted Image']['decoded_sha256'] ?? null) !== hash('sha256', $paintedPayload)
    || str_contains($plainText, 'WordPress Fake Text Object Image Payload Noise')
    || str_contains($plainText, 'WordPress Painted Image Payload Noise')
) {
    throw new RuntimeException('Image XObject text-object boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-text-object-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Image XObject Do is ignored while the content stream is inside BT/ET',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'text_object_do_unpainted' => ($entriesByName['Fake Text Image']['invoked'] ?? true) === false,
    'text_object_do_invocation_count' => $entriesByName['Fake Text Image']['invocation_count'] ?? null,
    'painted_sibling_invoked' => ($entriesByName['Painted Image']['invoked'] ?? false) === true,
    'painted_sibling_bbox' => $entriesByName['Painted Image']['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Fake Text Object Image Payload Noise')
        || str_contains($plainText, 'WordPress Painted Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-text-object-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
