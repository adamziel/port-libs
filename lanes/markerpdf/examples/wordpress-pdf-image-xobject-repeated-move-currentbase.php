<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Repeated Move Image Intro) Tj ET\n"
    . "q 10 10 m 100 100 m 120 100 l 120 120 l 100 120 l h W n 150 0 0 150 0 0 cm /Repeated#20Move#20Image Do Q\n"
    . "q 10 10 m 30 10 l 30 30 l 10 30 l h 100 100 m 120 100 l 120 120 l 100 120 l h W n 150 0 0 150 0 0 cm /Multi#20Subpath#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Repeated Move Image Outro) Tj ET';
$repeatedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Repeated Move Image Payload Noise) Tj ET';
$multiPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Multi Subpath Image Payload Noise) Tj ET';
$repeatedCompressed = gzcompress($repeatedPayload);
$multiCompressed = gzcompress($multiPayload);
if (!is_string($repeatedCompressed) || !is_string($multiCompressed)) {
    throw new RuntimeException('Unable to compress repeated move image XObject smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Repeated#20Move#20Image 5 0 R /Multi#20Subpath#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($repeatedCompressed) . " >>\nstream\n{$repeatedCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($multiCompressed) . " >>\nstream\n{$multiCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$repeated = $entriesByName['Repeated Move Image'] ?? [];
$multi = $entriesByName['Multi Subpath Image'] ?? [];
$metadata = [
    'source' => 'native-pdf-image-xobject-repeated-move-boundary-currentbase',
    'upstream_boundary' => 'PDF path construction replaces a dangling repeated m before later clipping; Image XObject review uses that parser boundary before media handoff',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'repeated_move_clip_bbox' => $repeated['invocation_clip_bboxes'][0] ?? null,
    'repeated_move_visible_bbox' => $repeated['image_visible_bbox'] ?? null,
    'multi_subpath_clip_bbox' => $multi['invocation_clip_bboxes'][0] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Repeated Move Image Payload Noise')
        || str_contains($plainText, 'WordPress Multi Subpath Image Payload Noise'),
];

if (
    $metadata['image_xobject_count'] !== 2
    || $metadata['invoked_image_xobject_count'] !== 2
    || $metadata['repeated_move_clip_bbox'] !== [100.0, 100.0, 120.0, 120.0]
    || $metadata['repeated_move_visible_bbox'] !== [100.0, 100.0, 120.0, 120.0]
    || $metadata['multi_subpath_clip_bbox'] !== [10.0, 10.0, 120.0, 120.0]
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('repeated moveto Image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-repeated-move-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
