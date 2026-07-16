<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Curve Clip Image Boundary Intro) Tj ET\n"
    . "q 40 0 0 20 0 0 cm .5 0 .5 .5 0 .5 c W n /Orphan#20C#20Image Do Q\n"
    . "q 40 0 0 20 50 0 cm .25 .25 .5 .5 v W n /Orphan#20V#20Image Do Q\n"
    . "q 40 0 0 20 100 0 cm .25 .25 .5 .5 y W n /Orphan#20Y#20Image Do Q\n"
    . "q 40 0 0 20 150 0 cm 0 0 m .5 0 .5 .5 0 .5 c W n /Valid#20Curve#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Curve Clip Image Boundary Outro) Tj ET';
$payloads = [
    'Orphan C Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Orphan C Curve Image Noise) Tj ET',
    'Orphan V Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Orphan V Curve Image Noise) Tj ET',
    'Orphan Y Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Orphan Y Curve Image Noise) Tj ET',
    'Valid Curve Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Valid Curve Image Noise) Tj ET',
];
$compressed = [];
foreach ($payloads as $name => $payload) {
    $compressedPayload = gzcompress($payload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException("Unable to compress {$name} smoke payload.");
    }
    $compressed[$name] = $compressedPayload;
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Orphan#20C#20Image 5 0 R /Orphan#20V#20Image 6 0 R /Orphan#20Y#20Image 7 0 R /Valid#20Curve#20Image 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan C Image']) . " >>\nstream\n{$compressed['Orphan C Image']}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan V Image']) . " >>\nstream\n{$compressed['Orphan V Image']}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Orphan Y Image']) . " >>\nstream\n{$compressed['Orphan Y Image']}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Valid Curve Image']) . " >>\nstream\n{$compressed['Valid Curve Image']}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$metadata = [
    'source' => 'native-pdf-image-xobject-curve-path-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; PDF curve path operators require a current point before clipping Image XObject placement',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'orphan_c_clip_applied' => $entriesByName['Orphan C Image']['clip_applied'] ?? true,
    'orphan_v_clip_applied' => $entriesByName['Orphan V Image']['clip_applied'] ?? true,
    'orphan_y_clip_applied' => $entriesByName['Orphan Y Image']['clip_applied'] ?? true,
    'orphan_c_visible_bbox' => $entriesByName['Orphan C Image']['image_visible_bbox'] ?? null,
    'orphan_v_visible_bbox' => $entriesByName['Orphan V Image']['image_visible_bbox'] ?? null,
    'orphan_y_visible_bbox' => $entriesByName['Orphan Y Image']['image_visible_bbox'] ?? null,
    'valid_curve_clip_bbox' => $entriesByName['Valid Curve Image']['invocation_clip_bboxes'][0] ?? null,
    'valid_curve_visible_bbox' => $entriesByName['Valid Curve Image']['image_visible_bbox'] ?? null,
    'payload_in_visible_text' => false,
];
foreach ($payloads as $payload) {
    if (str_contains($plainText, $payload)) {
        $metadata['payload_in_visible_text'] = true;
        break;
    }
}

if (
    $lines !== ['Curve Clip Image Boundary Intro', 'Curve Clip Image Boundary Outro']
    || $metadata['image_xobject_count'] !== 4
    || $metadata['invoked_image_xobject_count'] !== 4
    || $metadata['orphan_c_clip_applied'] !== false
    || $metadata['orphan_v_clip_applied'] !== false
    || $metadata['orphan_y_clip_applied'] !== false
    || $metadata['orphan_c_visible_bbox'] !== [0.0, 0.0, 40.0, 20.0]
    || $metadata['orphan_v_visible_bbox'] !== [50.0, 0.0, 90.0, 20.0]
    || $metadata['orphan_y_visible_bbox'] !== [100.0, 0.0, 140.0, 20.0]
    || $metadata['valid_curve_clip_bbox'] !== [150.0, 0.0, 170.0, 10.0]
    || $metadata['valid_curve_visible_bbox'] !== [150.0, 0.0, 170.0, 10.0]
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('Curve path image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-curve-path-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
