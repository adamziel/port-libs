<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before tagged images) Tj ET\n"
    . "/Figure << /MCID 7 /Alt (Hero Figure Alt Review) /ActualText (Hero Figure Actual Review) >> BDC q 20 0 0 10 72 690 cm /Tagged#20Image Do Q EMC\n"
    . "/Figure /Image#20Props BDC q 12 0 0 6 104 690 cm /Property#20Image Do Q EMC\n"
    . 'BT /F1 12 Tf 72 660 Td (After tagged images) Tj ET';
$taggedPayload = 'BT /F1 12 Tf 72 720 Td (Tagged Image Payload Noise) Tj ET';
$propertyPayload = 'BT /F1 12 Tf 72 720 Td (Property Image Payload Noise) Tj ET';
$taggedCompressed = gzcompress($taggedPayload);
$propertyCompressed = gzcompress($propertyPayload);
if (!is_string($taggedCompressed) || !is_string($propertyCompressed)) {
    throw new RuntimeException('Unable to compress marked-content image smoke fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Properties << /Image#20Props << /MCID 8 /Alt (Property Figure Alt Review) >> >> /XObject << /Tagged#20Image 5 0 R /Property#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($taggedCompressed) . " >>\nstream\n{$taggedCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($propertyCompressed) . " >>\nstream\n{$propertyCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$textLines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$tagged = $entriesByName['Tagged Image'] ?? [];
$property = $entriesByName['Property Image'] ?? [];
$taggedMarked = $tagged['invocation_marked_content'][0]['stack'][0] ?? [];
$propertyMarked = $property['invocation_marked_content'][0]['stack'][0] ?? [];

$visibleTextExcludesMarkedImageReplacement = $plainText === "Before tagged images\nAfter tagged images"
    && !str_contains($plainText, 'Hero Figure Alt Review')
    && !str_contains($plainText, 'Hero Figure Actual Review')
    && !str_contains($plainText, 'Property Figure Alt Review');
$payloadInVisibleText = str_contains($plainText, 'Tagged Image Payload Noise')
    || str_contains($plainText, 'Property Image Payload Noise');

if (
    ($review['image_xobject_count'] ?? null) !== 2
    || ($review['invoked_image_xobject_count'] ?? null) !== 2
    || ($tagged['marked_content_review_only'] ?? null) !== true
    || ($property['marked_content_review_only'] ?? null) !== true
    || ($taggedMarked['mcid'] ?? null) !== 7
    || ($taggedMarked['actual_text'] ?? null) !== 'Hero Figure Actual Review'
    || ($propertyMarked['mcid'] ?? null) !== 8
    || ($propertyMarked['alt_text'] ?? null) !== 'Property Figure Alt Review'
    || !$visibleTextExcludesMarkedImageReplacement
    || $payloadInVisibleText
) {
    throw new RuntimeException('Marked-content image XObject smoke invariants failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-marked-content-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'tagged_image_mcid' => $taggedMarked['mcid'],
    'property_image_mcid' => $propertyMarked['mcid'],
    'tagged_alt_reviewed' => ($taggedMarked['alt_text'] ?? null) === 'Hero Figure Alt Review',
    'tagged_actual_text_reviewed' => ($taggedMarked['actual_text'] ?? null) === 'Hero Figure Actual Review',
    'property_alt_reviewed' => ($propertyMarked['alt_text'] ?? null) === 'Property Figure Alt Review',
    'visible_text_lines' => $textLines,
    'visible_text_excludes_marked_image_replacement' => $visibleTextExcludesMarkedImageReplacement,
    'payload_in_visible_text' => $payloadInVisibleText,
    'marked_content_review_only' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-marked-content-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-review="marked-content-xobject"';
echo ' data-marker-tagged-mcid="' . htmlspecialchars((string) $metadata['tagged_image_mcid'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-property-mcid="' . htmlspecialchars((string) $metadata['property_image_mcid'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-visible-text-clean="' . htmlspecialchars($metadata['visible_text_excludes_marked_image_replacement'] ? 'true' : 'false', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Tagged PDF image XObject review placeholder" style="background: linear-gradient(90deg, rgb(36,106,168), rgb(232,177,52)); width: 128px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
