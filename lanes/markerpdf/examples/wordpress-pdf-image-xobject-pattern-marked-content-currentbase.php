<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before marked pattern image) Tj ET\n"
    . "/Figure << /MCID 17 >> BDC /Pattern cs /Figure#20Tile scn 0 0 20 10 re f EMC\n"
    . "/Figure /Pattern#20Props BDC /Pattern cs /Property#20Tile scn 0 0 20 10 re f EMC\n"
    . 'BT /F1 12 Tf 72 660 Td (After marked pattern image) Tj ET';
$figurePatternContent = 'q 6 0 0 3 1 2 cm /Figure#20Pattern#20Image Do Q';
$propertyPatternContent = 'q 4 0 0 2 2 1 cm /Property#20Pattern#20Image Do Q';
$figurePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Figure Pattern Image Payload Noise) Tj ET';
$propertyPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Property Pattern Image Payload Noise) Tj ET';
$figureCompressed = gzcompress($figurePayload);
$propertyCompressed = gzcompress($propertyPayload);
if (!is_string($figureCompressed) || !is_string($propertyCompressed)) {
    throw new RuntimeException('Unable to compress marked pattern image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Properties << /Pattern#20Props << /MCID 18 >> >> /Pattern << /Figure#20Tile 11 0 R /Property#20Tile 12 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($figureCompressed) . " >>\nstream\n{$figureCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($propertyCompressed) . " >>\nstream\n{$propertyCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Figure#20Pattern#20Image 5 0 R >> >> /Length " . strlen($figurePatternContent) . " >>\nstream\n{$figurePatternContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Matrix [1 0 0 1 5 6] /Resources << /XObject << /Property#20Pattern#20Image 6 0 R >> >> /Length " . strlen($propertyPatternContent) . " >>\nstream\n{$propertyPatternContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$textLines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$figure = $entriesByName['Figure Pattern Image'] ?? [];
$property = $entriesByName['Property Pattern Image'] ?? [];
$figureMarked = $figure['invocation_marked_content'][0]['stack'][0] ?? [];
$propertyMarked = $property['invocation_marked_content'][0]['stack'][0] ?? [];
$payloadInVisibleText = str_contains($plainText, 'WordPress Figure Pattern Image Payload Noise')
    || str_contains($plainText, 'WordPress Property Pattern Image Payload Noise');

if (
    ($review['image_xobject_count'] ?? null) !== 2
    || ($review['invoked_image_xobject_count'] ?? null) !== 2
    || ($figure['pattern_resource_name'] ?? null) !== 'Figure Tile'
    || ($property['pattern_resource_name'] ?? null) !== 'Property Tile'
    || ($figure['marked_content_review_only'] ?? null) !== true
    || ($property['marked_content_review_only'] ?? null) !== true
    || ($figureMarked['mcid'] ?? null) !== 17
    || ($propertyMarked['mcid'] ?? null) !== 18
    || ($propertyMarked['property_resource_name'] ?? null) !== 'Pattern Props'
    || ($propertyMarked['property_source'] ?? null) !== 'Resources.Properties'
    || $plainText !== "Before marked pattern image\nAfter marked pattern image"
    || $payloadInVisibleText
) {
    throw new RuntimeException('Marked pattern Image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-pattern-marked-content-currentbase',
    'upstream_boundary' => 'marker.pdf image extraction handoff plus PDF PatternType 1 paint operation marked-content review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'figure_pattern_mcid' => $figureMarked['mcid'] ?? null,
    'property_pattern_mcid' => $propertyMarked['mcid'] ?? null,
    'property_resource_name' => $propertyMarked['property_resource_name'] ?? null,
    'property_source' => $propertyMarked['property_source'] ?? null,
    'figure_pattern_resource_name' => $figure['pattern_resource_name'] ?? null,
    'property_pattern_resource_name' => $property['pattern_resource_name'] ?? null,
    'payload_in_visible_text' => $payloadInVisibleText,
    'marked_content_review_only' => true,
    'visible_text_lines' => $textLines,
];

echo '<!-- markerpdf:pdf-image-xobject-pattern-marked-content-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-review="pattern-marked-content-xobject"';
echo ' data-marker-figure-mcid="' . htmlspecialchars((string) $metadata['figure_pattern_mcid'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-property-mcid="' . htmlspecialchars((string) $metadata['property_pattern_mcid'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-property-source="' . htmlspecialchars((string) $metadata['property_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="PDF tiling pattern image XObject review placeholder" style="background: linear-gradient(90deg, rgb(30,90,130), rgb(218,170,64)); width: 128px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n\n";
foreach ($textLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
