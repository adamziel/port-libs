<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /CharProcs << /A 3 0 R >> /Private << /Subtype /Type3 >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-subtype-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-subtype-boundary',
    'font_boundary_sources' => [
        'top-level /Subtype /Type1 remains a simple font',
        'nested private /Subtype /Type3 decoy ignored',
        'top-level /CharProcs ignored unless the font subtype is Type3',
    ],
    'requires_top_level_type3_subtype' => $plainText === 'Visible fallback content',
    'fallback_content_preserved' => $lines === ['Visible fallback content'],
    'false_charproc_suppression_prevented' => $plainText !== '',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
