<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPatternBoundaryPdf = static function (): string {
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before Pattern Filter Boundary) Tj ET\n"
        . "/Pattern cs /Bad#20Pattern scn 0 0 20 10 re f\n"
        . "/Pattern cs /Safe#20Pattern scn 25 0 20 10 re f\n"
        . 'BT /F1 12 Tf 72 660 Td (After Pattern Filter Boundary) Tj ET';

    $badPatternContent = 'q 20 0 0 20 0 0 cm /Bad#20Pattern#20Image Do Q';
    $badPatternEncoded = strtoupper(bin2hex($badPatternContent))
        . '> q 20 0 0 20 0 0 cm /Bad#20Trailing#20Image Do Q';

    $safePatternContent = 'q 20 0 0 20 0 0 cm /Safe#20Pattern#20Image Do Q';
    $safePatternEncoded = strtoupper(bin2hex($safePatternContent)) . ">\n \t";

    $badPayload = 'BT /F1 12 Tf 72 720 Td (Bad Pattern Image Payload Leak) Tj ET';
    $badTrailingPayload = 'BT /F1 12 Tf 72 720 Td (Bad Trailing Pattern Image Payload Leak) Tj ET';
    $safePayload = 'BT /F1 12 Tf 72 720 Td (Safe Pattern Image Payload Noise) Tj ET';
    $badCompressed = gzcompress($badPayload);
    $badTrailingCompressed = gzcompress($badTrailingPayload);
    $safeCompressed = gzcompress($safePayload);
    if (!is_string($badCompressed) || !is_string($badTrailingCompressed) || !is_string($safeCompressed)) {
        throw new RuntimeException('Unable to compress focused tiling pattern boundary image payloads.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Bad#20Pattern 11 0 R /Safe#20Pattern 12 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($safeCompressed) . " >>\nstream\n{$safeCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badTrailingCompressed) . " >>\nstream\n{$badTrailingCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Bad#20Pattern#20Image 5 0 R /Bad#20Trailing#20Image 7 0 R >> >> /Filter /ASCIIHexDecode /Length " . strlen($badPatternEncoded) . " >>\nstream\n{$badPatternEncoded}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Resources << /XObject << /Safe#20Pattern#20Image 6 0 R >> >> /Filter /ASCIIHexDecode /Length " . strlen($safePatternEncoded) . " >>\nstream\n{$safePatternEncoded}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$pdf = $buildPatternBoundaryPdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$metadata = [
    'scenario' => 'wordpress_pdf_stream_filter_stack_pattern_boundary_currentbase',
    'source_truth' => 'sddai/markerPDF native PDF parser stream filters and PDF explicit filter EOD boundaries',
    'visible_text_lines' => $lines,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'safe_pattern_filter_stack_preserved' => isset($entriesByName['Safe Pattern Image']),
    'safe_pattern_parent_object' => $entriesByName['Safe Pattern Image']['parent_pattern_object'] ?? null,
    'malformed_pattern_filter_stack_rejected' => !isset($entriesByName['Bad Pattern Image']),
    'raw_trailing_pattern_payload_excluded' => !isset($entriesByName['Bad Trailing Image']),
    'visible_payload_text_excluded' => !str_contains($plainText, 'Bad Pattern Image Payload Leak')
        && !str_contains($plainText, 'Bad Trailing Pattern Image Payload Leak')
        && !str_contains($plainText, 'Safe Pattern Image Payload Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'dependency_closure' => 'reuses native PHP stream filter decoding and image-review traversal; no new support component',
];

echo '<!-- markerpdf-stream-filter-stack-pattern-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
