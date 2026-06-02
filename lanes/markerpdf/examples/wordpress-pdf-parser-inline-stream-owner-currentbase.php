<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$inlineOwnerPayload = 'BT /F1 12 Tf 72 660 Td (Inline Stream Owner Leak) Tj ET';
$inlineImagePayload = "<< /Length " . strlen($inlineOwnerPayload) . " >>\n"
    . "stream\n{$inlineOwnerPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n";
$content = "BT /F1 12 Tf 72 720 Td (Before Inline Owner Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 ID\n{$inlineImagePayload}\nEI\n"
    . 'BT /F1 12 Tf 72 700 Td (After Inline Owner Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< >>\nstream\n{$content}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

if ($lines !== ['Before Inline Owner Boundary', 'After Inline Owner Boundary']) {
    throw new RuntimeException('Expected inline-image owner boundary fixture to preserve text around the image bytes.');
}

if (str_contains($plainText, 'Inline Stream Owner Leak') || str_contains($plainText, '20 0 obj')) {
    throw new RuntimeException('Expected inline-image stream-owner decoy bytes to stay out of WordPress paragraphs.');
}

echo '<!-- markerpdf-parser-inline-stream-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-content-stream-owner-parser',
    'native_boundary' => 'unfiltered content stream endstream scanning skips raw inline image payload bytes before object ownership',
    'visible_text_imported' => $lines === ['Before Inline Owner Boundary', 'After Inline Owner Boundary'],
    'inline_payload_text_excluded' => !str_contains($plainText, 'Inline Stream Owner Leak'),
    'fake_object_header_excluded' => !str_contains($plainText, '20 0 obj'),
    'fake_endstream_owner_excluded' => !str_contains($plainText, 'endstream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'page_labels' => $extractor->extractPageLabels($pdf),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
