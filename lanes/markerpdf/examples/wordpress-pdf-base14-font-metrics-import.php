<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$helveticaContent = 'BT /Fhelv 12 Tf 1 0 0 1 72 720 Tm (Ill) Tj 1 0 0 1 93 720 Tm (Word) Tj ET '
    . 'BT /Fhelv 12 Tf 1 0 0 1 72 704 Tm (WWW) Tj 1 0 0 1 104 704 Tm (Import) Tj ET';
$timesContent = 'BT /Ftimes 12 Tf 1 0 0 1 72 688 Tm (iii) Tj 1 0 0 1 95 688 Tm (Word) Tj ET';
$courierContent = 'BT /Fcourier 12 Tf 1 0 0 1 72 672 Tm (Courier) Tj 1 0 0 1 126 672 Tm (Text) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhelv 2 0 R /Ftimes 5 0 R /Fcourier 8 0 R >> >> /Contents [3 0 R 6 0 R 9 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($helveticaContent) . " >>\nstream\n{$helveticaContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($timesContent) . " >>\nstream\n{$timesContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier-BoldOblique >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($courierContent) . " >>\nstream\n{$courierContent}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-base14-font-metrics ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-base14-simple-font-width-metrics',
    'font_width_sources' => ['Helvetica AFM', 'Times-Roman AFM', 'Courier AFM'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
