<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = "\x01EI BT /F1 12 Tf 4 24 Td (WP Form Inline ColorSpace Payload Noise) Tj ET \x02\x03";
$formStream = "BT /F1 12 Tf 4 36 Td (WP Form Inline ColorSpace Before) Tj ET\n"
    . "BI /W 1 /H 1 /CS /FormRGB /BPC 8 ID\n"
    . $payload . "\nEI\n"
    . "BT /F1 12 Tf 4 18 Td (WP Form Inline ColorSpace After) Tj ET";
$pageStream = "q 1 0 0 1 72 650 cm /Inline#20Form Do Q";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /XObject << /Inline#20Form 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n{$pageStream}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 60] /Resources << /Font << /F1 6 0 R >> /ColorSpace << /FormRGB /DeviceRGB >> >> /Length " . strlen($formStream) . " >>\nstream\n{$formStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expectedLines = [
    'WP Form Inline ColorSpace Before',
    'WP Form Inline ColorSpace After',
];

$evidence = [
    'scenario' => 'wordpress_pdf_form_inline_image_colorspace_currentbase',
    'native_boundary' => 'Form XObject local /Resources /ColorSpace map used while suppressing inline image payload before Gutenberg paragraph import',
    'paragraphs' => $lines,
    'form_local_colorspace_used' => $lines === $expectedLines && $plainText === implode("\n", $expectedLines),
    'inline_image_payload_not_imported' => !str_contains($plainText, 'WP Form Inline ColorSpace Payload Noise'),
    'page_resource_fallback_not_required' => !str_contains($plainText, 'FormRGB'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    foreach (['form_local_colorspace_used', 'inline_image_payload_not_imported', 'page_resource_fallback_not_required'] as $flag) {
        if (($evidence[$flag] ?? false) !== true) {
            throw new RuntimeException('Failed markerPDF Form inline image ColorSpace smoke: ' . $flag);
        }
    }

    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_form_inline_image_colorspace_currentbase\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
