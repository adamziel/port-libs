<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObjectBody = static function (string $content): string {
    return "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
};

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current XRef Import) Tj ET';
$freeStaleContent = 'BT /F1 12 Tf 72 720 Td (Free Stale Import) Tj ET';
$pdf = "%PDF-1.4\n";
$offsetOne = strlen($pdf);
$pdf .= "1 0 obj\n" . $streamObjectBody($currentContent) . "\nendobj\n";
$pdf .= "2 0 obj\n" . $streamObjectBody($freeStaleContent) . "\nendobj\n";
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 3\n";
$pdf .= "0000000000 65535 f \n";
$pdf .= str_pad((string) $offsetOne, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
$pdf .= "0000000000 65535 f \n";
$pdf .= "trailer\n<< /Size 3 >>\nstartxref\n12\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf:pdf-classic-xref-rebuild ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'paragraphs' => $lines,
    'declared_startxref' => 12,
    'actual_xref_offset' => $xrefOffset,
    'classic_xref_table_repaired' => true,
    'free_stale_stream_excluded' => !str_contains($plainText, 'Free Stale Import'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
