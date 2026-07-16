<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Zero Area Image Intro) Tj ET\n"
    . "q 0 0 0 10 72 690 cm /Zero#20Width#20Image Do Q\n"
    . "q 10 0 0 0 90 690 cm /Zero#20Height#20Image Do Q\n"
    . "q 12 0 0 6 110 690 cm /Visible#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Zero Area Image Outro) Tj ET';
$zeroWidthPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Zero Width CTM Image Payload Noise) Tj ET';
$zeroHeightPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Zero Height CTM Image Payload Noise) Tj ET';
$visiblePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Visible CTM Image Payload Noise) Tj ET';
$zeroWidthCompressed = gzcompress($zeroWidthPayload);
$zeroHeightCompressed = gzcompress($zeroHeightPayload);
$visibleCompressed = gzcompress($visiblePayload);
if (!is_string($zeroWidthCompressed) || !is_string($zeroHeightCompressed) || !is_string($visibleCompressed)) {
    throw new RuntimeException('Unable to compress zero-area image XObject smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Zero#20Width#20Image 5 0 R /Zero#20Height#20Image 6 0 R /Visible#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($zeroWidthCompressed) . " >>\nstream\n{$zeroWidthCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($zeroHeightCompressed) . " >>\nstream\n{$zeroHeightCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 3
    || ($entriesByName['Zero Width Image']['painted_invocation_count'] ?? null) !== 0
    || ($entriesByName['Zero Height Image']['painted_invocation_count'] ?? null) !== 0
    || ($entriesByName['Visible Image']['painted_invocation_count'] ?? null) !== 1
    || ($entriesByName['Zero Width Image']['geometry_paint_suppression_reasons'] ?? null) !== ['zero_area_ctm']
    || ($entriesByName['Zero Height Image']['geometry_paint_suppression_reasons'] ?? null) !== ['zero_area_ctm']
    || ($entriesByName['Visible Image']['geometry_paint_suppressed'] ?? true) !== false
    || str_contains($plainText, 'WordPress Zero Width CTM Image Payload Noise')
    || str_contains($plainText, 'WordPress Zero Height CTM Image Payload Noise')
    || str_contains($plainText, 'WordPress Visible CTM Image Payload Noise')
) {
    throw new RuntimeException('Image XObject zero-area CTM boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-zero-area-ctm-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; zero-area image CTMs are reviewable Do invocations without painted media geometry',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'zero_width_invoked' => $entriesByName['Zero Width Image']['invoked'] ?? null,
    'zero_width_painted_invocation_count' => $entriesByName['Zero Width Image']['painted_invocation_count'] ?? null,
    'zero_width_suppression_reasons' => $entriesByName['Zero Width Image']['geometry_paint_suppression_reasons'] ?? [],
    'zero_height_invoked' => $entriesByName['Zero Height Image']['invoked'] ?? null,
    'zero_height_painted_invocation_count' => $entriesByName['Zero Height Image']['painted_invocation_count'] ?? null,
    'zero_height_suppression_reasons' => $entriesByName['Zero Height Image']['geometry_paint_suppression_reasons'] ?? [],
    'visible_image_painted' => ($entriesByName['Visible Image']['painted_invocation_count'] ?? null) === 1,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Zero Width CTM Image Payload Noise')
        || str_contains($plainText, 'WordPress Zero Height CTM Image Payload Noise')
        || str_contains($plainText, 'WordPress Visible CTM Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-zero-area-ctm-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
