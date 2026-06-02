<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$visible = 'BT /F1 12 Tf 72 720 Td (Visible Token Boundary) Tj ET';
$privatePayload = 'BT /F1 12 Tf 72 700 Td (PieceInfo Token Stream Leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /WP << /Private 4 0 R >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Note (919 0 obj fake owner before stream) /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Visible Token Boundary'] || str_contains($plainText, 'PieceInfo Token Stream Leak')) {
    throw new RuntimeException('Expected token-aware stream owner parsing to exclude PieceInfo private payload text.');
}

echo '<!-- markerpdf-token-stream-object-boundary-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-token-aware-direct-object-ranges',
    'native_boundary' => 'direct stream owner lookup ignores fake n n obj tokens inside PDF strings before stream payloads',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'visible_lines' => $lines,
    'excluded_pieceinfo_private_stream_text' => !str_contains($plainText, 'PieceInfo Token Stream Leak'),
    'ignored_fake_object_header_before_stream' => !str_contains($plainText, '919 0 obj'),
    'fallback_page_labels' => $extractor->extractPageLabels($pdf),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
