<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = '';
$expected = [];
foreach ([
    ['ActualText', 'ActualText'],
    ['Alt', 'Alt'],
] as [$propertyName, $label]) {
    $before = "Before {$label} Replacement Stray";
    $replacement = "Visible {$label} Replacement Before Stray";
    $after = "Visible After {$label} Replacement Stray";
    $payloadNoise = "{$label} Replacement Payload Noise";
    $expected[] = $before;
    $expected[] = $replacement;
    $expected[] = $after;
    $content .= "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td ({$payloadNoise}) Tj ET rawtail\n"
        . "EI\n"
        . "/Span << /{$propertyName} ({$replacement}) >> BDC EMC\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td ({$after}) Tj ET\n";
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

if ($lines !== $expected) {
    throw new RuntimeException('Expected replacement-only marked content after inline image boundaries to survive extraction.');
}

foreach (['ActualText Replacement Payload Noise', 'Alt Replacement Payload Noise', 'rawtail', 'JBIG2Decode'] as $forbidden) {
    if (str_contains($plainText, $forbidden)) {
        throw new RuntimeException('Expected inline image payload bytes to stay out of WordPress text extraction.');
    }
}

echo '<!-- markerpdf-inline-image-marked-replacement-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'preview-only inline image EI closes before direct replacement-only marked content',
    'actual_text_replacement_preserved' => in_array('Visible ActualText Replacement Before Stray', $lines, true),
    'alt_replacement_preserved' => in_array('Visible Alt Replacement Before Stray', $lines, true),
    'payload_text_suppressed' => !str_contains($plainText, 'Replacement Payload Noise') && !str_contains($plainText, 'rawtail'),
    'stray_ei_after_replacement_ignored' => in_array('Visible After Alt Replacement Stray', $lines, true),
    'line_count' => count($lines),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
