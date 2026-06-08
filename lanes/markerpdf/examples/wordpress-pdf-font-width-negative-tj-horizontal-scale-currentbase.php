<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$widths = implode(' ', array_fill(0, 45, '1000'));
$content = 'BT /Fmirror 12 Tf -100 Tz 1 0 0 1 72 720 Tm [(AB) -1500 (CD)] TJ T* '
    . '100 Tz 1 0 0 1 72 704 Tm [(EF) -1500 (GH)] TJ T* '
    . '-100 Tz 1 0 0 1 72 688 Tm [(IJ) 1500 (KL)] TJ ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] "
    . "/Resources << /Font << /Fmirror 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
    . "/Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 76 /Widths [{$widths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n"
    . $content
    . "\nendstream\nendobj\n"
    . "xref\n0 6\n0000000000 65535 f \n"
    . "trailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n0\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expectedLines = ['AB CD', 'EF GH', 'IJKL'];

if ($lines !== $expectedLines || $plainText !== implode("\n", $expectedLines)) {
    throw new RuntimeException('Expected mirrored horizontal TJ advances to preserve WordPress paragraph word gaps.');
}

echo '<!-- markerpdf:pdf-font-width-negative-tj-horizontal-scale-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-simple-font-width-negative-tj-horizontal-scale-currentbase',
    'mirrored_horizontal_tj_gap_preserved' => true,
    'positive_scale_tj_gap_preserved' => true,
    'mirrored_backtrack_compacted' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
