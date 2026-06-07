<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image BBox Operand Tail Intro) Tj ET\n"
    . "q 40 0 0 20 100 200 cm /Bad#20BBox#20Form Do Q\n"
    . "q 40 0 0 20 200 200 cm /Good#20BBox#20Form Do Q\n"
    . "/Pattern cs /Bad#20BBox#20Tile scn 0 0 20 10 re f\n"
    . "/Pattern cs /Good#20BBox#20Tile scn 0 0 20 10 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image BBox Operand Tail Outro) Tj ET';
$badFormContent = 'q 4 0 0 3 0.5 0.25 cm /Bad#20Nested#20Image Do Q';
$goodFormContent = 'q 4 0 0 3 0.5 0.25 cm /Good#20Nested#20Image Do Q';
$badPatternContent = 'q 5 0 0 2 4 4 cm /Bad#20Pattern#20Image Do Q';
$goodPatternContent = 'q 5 0 0 2 4 4 cm /Good#20Pattern#20Image Do Q';

$payloads = [
    'Bad Nested Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Form BBox Payload Noise) Tj ET',
    'Good Nested Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Valid Form BBox Payload Noise) Tj ET',
    'Bad Pattern Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Pattern BBox Payload Noise) Tj ET',
    'Good Pattern Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Valid Pattern BBox Payload Noise) Tj ET',
];
$compressed = [];
foreach ($payloads as $name => $payload) {
    $encoded = gzcompress($payload);
    if (!is_string($encoded)) {
        throw new RuntimeException("Unable to compress {$name} BBox operand-tail smoke payload.");
    }

    $compressed[$name] = $encoded;
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Bad#20BBox#20Form 5 0 R /Good#20BBox#20Form 7 0 R >> /Pattern << /Bad#20BBox#20Tile 11 0 R /Good#20BBox#20Tile 12 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] 99 /Resources << /XObject << /Bad#20Nested#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($badFormContent) . " >>\nstream\n{$badFormContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Bad Nested Image']) . " >>\nstream\n{$compressed['Bad Nested Image']}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Resources << /XObject << /Good#20Nested#20Image 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($goodFormContent) . " >>\nstream\n{$goodFormContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Good Nested Image']) . " >>\nstream\n{$compressed['Good Nested Image']}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Bad Pattern Image']) . " >>\nstream\n{$compressed['Bad Pattern Image']}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 2 2] 99 /XStep 20 /YStep 10 /Resources << /XObject << /Bad#20Pattern#20Image 9 0 R >> >> /Length " . strlen($badPatternContent) . " >>\nstream\n{$badPatternContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 2 2] /XStep 20 /YStep 10 /Resources << /XObject << /Good#20Pattern#20Image 13 0 R >> >> /Length " . strlen($goodPatternContent) . " >>\nstream\n{$goodPatternContent}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Good Pattern Image']) . " >>\nstream\n{$compressed['Good Pattern Image']}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$payloadInVisibleText = false;
foreach ($payloads as $payload) {
    $payloadInVisibleText = $payloadInVisibleText || str_contains($plainText, $payload);
}

$malformedFormTailIgnored = ($entriesByName['Bad Nested Image']['image_visible_bbox'] ?? null) === [120.0, 205.0, 280.0, 265.0]
    && ($entriesByName['Bad Nested Image']['clip_applied'] ?? true) === false;
$validFormClips = ($entriesByName['Good Nested Image']['image_visible_bbox'] ?? null) === [220.0, 205.0, 240.0, 220.0]
    && ($entriesByName['Good Nested Image']['clip_reduces_painted_bbox'] ?? false) === true;
$malformedPatternTailIgnored = ($entriesByName['Bad Pattern Image']['image_visible_bbox'] ?? null) === [4.0, 4.0, 9.0, 6.0]
    && ($entriesByName['Bad Pattern Image']['clip_excludes_image'] ?? true) === false;
$validPatternExcludes = array_key_exists('image_visible_bbox', $entriesByName['Good Pattern Image'])
    && $entriesByName['Good Pattern Image']['image_visible_bbox'] === null
    && ($entriesByName['Good Pattern Image']['clip_excludes_image'] ?? false) === true;

if (
    ($review['image_xobject_count'] ?? 0) !== 4
    || ($review['invoked_image_xobject_count'] ?? 0) !== 4
    || !$malformedFormTailIgnored
    || !$validFormClips
    || !$malformedPatternTailIgnored
    || !$validPatternExcludes
    || $payloadInVisibleText
) {
    throw new RuntimeException('Image XObject BBox operand-tail smoke failed: ' . json_encode([
        'image_xobject_count' => $review['image_xobject_count'] ?? null,
        'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
        'malformed_form_tail_ignored' => $malformedFormTailIgnored,
        'valid_form_clips' => $validFormClips,
        'malformed_pattern_tail_ignored' => $malformedPatternTailIgnored,
        'valid_pattern_excludes' => $validPatternExcludes,
        'payload_in_visible_text' => $payloadInVisibleText,
        'entry_names' => array_keys($entriesByName),
        'good_pattern_visible_bbox' => $entriesByName['Good Pattern Image']['image_visible_bbox'] ?? null,
        'good_pattern_clip_excludes_image' => $entriesByName['Good Pattern Image']['clip_excludes_image'] ?? null,
        'good_pattern_invocation_clip_bboxes' => $entriesByName['Good Pattern Image']['invocation_clip_bboxes'] ?? null,
    ], JSON_UNESCAPED_SLASHES));
}

$metadata = [
    'source' => 'native-pdf-image-xobject-bbox-operand-tail-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image handoff; malformed Form/Pattern BBox dictionary values with extra operands are ignored before image clipping',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'malformed_form_bbox_tail_ignored' => $malformedFormTailIgnored,
    'valid_form_bbox_clips' => $validFormClips,
    'malformed_pattern_bbox_tail_ignored' => $malformedPatternTailIgnored,
    'valid_pattern_bbox_excludes' => $validPatternExcludes,
    'payload_in_visible_text' => $payloadInVisibleText,
];

echo '<!-- markerpdf:pdf-image-xobject-bbox-operand-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
