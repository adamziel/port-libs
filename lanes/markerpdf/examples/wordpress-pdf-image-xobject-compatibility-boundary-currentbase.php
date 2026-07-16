<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before compatibility images) Tj ET';
$ignoredPaint = 'BX q 18 0 0 9 72 690 cm /Compatibility#20Image Do Q EX';
$paintedPaint = 'q 12 0 0 6 104 690 cm /Painted#20Compatibility#20Image Do Q';
$after = 'BT /F1 12 Tf 72 660 Td (After compatibility images) Tj ET';
$content = $before . "\n" . $ignoredPaint . "\n" . $paintedPaint . "\n" . $after;
$compatibilityPayload = 'BT /F1 12 Tf 72 720 Td (Compatibility Image Payload Noise) Tj ET';
$paintedPayload = 'BT /F1 12 Tf 72 720 Td (Painted Compatibility Image Payload Noise) Tj ET';
$compatibilityCompressed = gzcompress($compatibilityPayload);
$paintedCompressed = gzcompress($paintedPayload);
if (!is_string($compatibilityCompressed) || !is_string($paintedCompressed)) {
    throw new RuntimeException('Unable to compress compatibility image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Compatibility#20Image 5 0 R /Painted#20Compatibility#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compatibilityCompressed) . " >>\nstream\n{$compatibilityCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($paintedCompressed) . " >>\nstream\n{$paintedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$compatibility = $entriesByName['Compatibility Image'] ?? [];
$painted = $entriesByName['Painted Compatibility Image'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$checks = [
    'compatibility_section_image_unpainted' => ($compatibility['invoked'] ?? null) === false
        && ($compatibility['invocation_count'] ?? null) === 0
        && array_key_exists('image_unit_bbox', $compatibility)
        && $compatibility['image_unit_bbox'] === null,
    'painted_image_after_compatibility_section_counted' => ($painted['invoked'] ?? null) === true
        && ($painted['invocation_count'] ?? null) === 1
        && ($painted['image_unit_bbox'] ?? null) === [104.0, 690.0, 116.0, 696.0],
    'image_payloads_excluded_from_text' => !str_contains($plainText, 'Compatibility Image Payload Noise')
        && !str_contains($plainText, 'Painted Compatibility Image Payload Noise'),
    'image_payloads_excluded_from_review_json' => !str_contains($encodedReview, $compatibilityPayload)
        && !str_contains($encodedReview, $paintedPayload),
    'visible_paragraphs_preserved' => $lines === ['Before compatibility images', 'After compatibility images'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

foreach ($checks as $name => $ok) {
    if (str_starts_with($name, 'executes_')) {
        continue;
    }

    if ($ok !== true) {
        throw new RuntimeException("Failed image XObject compatibility smoke check: {$name}");
    }
}

echo "<!-- markerpdf-image-xobject-compatibility-boundary-currentbase -->\n";
foreach ($checks as $name => $ok) {
    echo $name . '=' . ($ok ? 'true' : 'false') . "\n";
}

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
