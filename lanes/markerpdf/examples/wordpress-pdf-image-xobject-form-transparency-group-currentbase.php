<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Grouped Form Image Intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Grouped#20Logo Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Grouped Form Image Outro) Tj ET';
$formContent = 'q 12 0 0 6 2 3 cm /Nested#20Group#20Image Do Q';
$nestedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Grouped Form Image Payload Noise) Tj ET';
$compressedNestedPayload = gzcompress($nestedPayload);
if (!is_string($compressedNestedPayload)) {
    throw new RuntimeException('Unable to compress grouped Form image XObject smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Grouped#20Logo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Group << /S /Transparency /CS /DeviceRGB /I true /K false >> /Resources << /XObject << /Nested#20Group#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 12 /Height 6 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedNestedPayload) . " >>\nstream\n{$compressedNestedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$invocationGroups = is_array($entry['invocation_form_transparency_groups'] ?? null)
    ? $entry['invocation_form_transparency_groups']
    : [];
$groupStack = is_array($invocationGroups[0]['stack'] ?? null) ? $invocationGroups[0]['stack'] : [];
$group = is_array($groupStack[0] ?? null) ? $groupStack[0] : [];

$metadata = [
    'source' => 'native-pdf-image-xobject-form-transparency-group-currentbase',
    'upstream_boundary' => 'PDF Form XObject transparency groups define compositing context for nested image XObjects before raster/media handoff',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'resource_path' => $entry['resource_path'] ?? null,
    'form_transparency_group_count' => $entry['form_transparency_group_count'] ?? null,
    'form_transparency_group_subtype' => $group['subtype'] ?? null,
    'form_transparency_group_color_space' => $group['color_space'] ?? null,
    'form_transparency_group_isolated' => $group['isolated'] ?? null,
    'form_transparency_group_knockout' => $group['knockout'] ?? null,
    'form_transparency_group_review_only' => $entry['form_transparency_group_review_only'] ?? false,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Grouped Form Image Payload Noise'),
];

if (
    $metadata['image_xobject_count'] !== 1
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['resource_path'] !== ['Grouped Logo', 'Nested Group Image']
    || $metadata['form_transparency_group_count'] !== 1
    || $metadata['form_transparency_group_subtype'] !== 'Transparency'
    || $metadata['form_transparency_group_color_space'] !== 'DeviceRGB'
    || $metadata['form_transparency_group_isolated'] !== true
    || $metadata['form_transparency_group_knockout'] !== false
    || $metadata['form_transparency_group_review_only'] !== true
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('Form transparency group image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-form-transparency-group-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
