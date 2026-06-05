<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Named stencil image import start) Tj ET\n"
    . "/Brand#20RGB cs 0.1 0.25 0.9 scn q 16 0 0 8 72 690 cm /Brand#20Stencil Do Q\n"
    . "/Pattern cs /Logo#20Pattern scn q 12 0 0 6 104 690 cm /Pattern#20Stencil Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Named stencil image import end) Tj ET';
$brandPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Brand Stencil Payload Noise) Tj ET';
$patternPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Pattern Stencil Payload Noise) Tj ET';
$brandCompressed = gzcompress($brandPayload);
$patternCompressed = gzcompress($patternPayload);
if (!is_string($brandCompressed) || !is_string($patternCompressed)) {
    throw new RuntimeException('Unable to compress named image mask stencil smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ColorSpace << /Brand#20RGB /DeviceRGB >> /Pattern << /Logo#20Pattern 11 0 R >> /XObject << /Brand#20Stencil 5 0 R /Pattern#20Stencil 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($brandCompressed) . " >>\nstream\n{$brandCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($patternCompressed) . " >>\nstream\n{$patternCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 1 1] /XStep 1 /YStep 1 /Resources << >> /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$brand = $entriesByName['Brand Stencil'] ?? [];
$pattern = $entriesByName['Pattern Stencil'] ?? [];
$brandColor = $brand['image_mask_paint_colors'][0] ?? [];
$patternColor = $pattern['image_mask_paint_colors'][0] ?? [];
if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($brandColor['color_space'] ?? null) !== 'Brand RGB'
    || ($brandColor['resolved_color_space'] ?? null) !== 'DeviceRGB'
    || ($brandColor['color_space_resource_name'] ?? null) !== 'Brand RGB'
    || ($brandColor['color_space_resolved_from_resources'] ?? false) !== true
    || ($brandColor['components'] ?? null) !== [0.1, 0.25, 0.9]
    || ($brandColor['valid_for_color_space'] ?? false) !== true
    || ($patternColor['color_space'] ?? null) !== 'Pattern'
    || ($patternColor['pattern_name'] ?? null) !== 'Logo Pattern'
    || ($patternColor['pattern_resolved_from_resources'] ?? false) !== true
    || ($patternColor['components'] ?? null) !== []
    || ($patternColor['valid_for_color_space'] ?? false) !== true
    || str_contains($plainText, 'WordPress Brand Stencil Payload Noise')
    || str_contains($plainText, 'WordPress Pattern Stencil Payload Noise')
) {
    throw new RuntimeException('Named image mask stencil color boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-imagemask-pattern-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff; ImageMask stencils use the current nonstroking color space and pattern before raster review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'brand_stencil_color_space' => $brandColor['color_space'] ?? null,
    'brand_stencil_resolved_color_space' => $brandColor['resolved_color_space'] ?? null,
    'brand_stencil_components' => $brandColor['components'] ?? [],
    'brand_stencil_valid_for_color_space' => $brandColor['valid_for_color_space'] ?? false,
    'pattern_stencil_color_space' => $patternColor['color_space'] ?? null,
    'pattern_stencil_pattern_name' => $patternColor['pattern_name'] ?? null,
    'pattern_stencil_resource_resolved' => $patternColor['pattern_resolved_from_resources'] ?? false,
    'pattern_stencil_components' => $patternColor['components'] ?? [],
    'pattern_stencil_valid_for_color_space' => $patternColor['valid_for_color_space'] ?? false,
    'payload_in_visible_text' => false,
    'image_mask_paint_color_review_only' => ($brand['image_mask_paint_color_review_only'] ?? false) === true
        && ($pattern['image_mask_paint_color_review_only'] ?? false) === true,
];

echo '<!-- markerpdf:pdf-image-xobject-imagemask-pattern-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
