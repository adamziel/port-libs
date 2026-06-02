<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fwide 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'BT /Fthin 12 Tf 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 98 704 Tm <58595A5B> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwide 2 0 R /Fthin 7 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+WideSimple /Encoding 6 0 R /FirstChar 65 /LastChar 73 /Widths [20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /BaseEncoding 8 0 R /Differences 9 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ThinSimple /Encoding 6 0 R /FirstChar 84 /LastChar 91 /Widths 22 0 R >>\nendobj\n"
    . "8 0 obj\n/WinAnsiEncoding\nendobj\n"
    . "9 0 obj\n[65 /W /i /d /e /B /l /o /c /k 84 /T /h /i /n /T /e /x /t]\nendobj\n"
    . "20 0 obj\n1000\nendobj\n"
    . "21 0 obj\n250\nendobj\n"
    . "22 0 obj\n[21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R]\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-simple-font-encoding-indirect-width-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-simple-font-indirect-encoding-width-boundary',
    'font_sources' => [
        'indirect /Encoding dictionary',
        'indirect /BaseEncoding name',
        'indirect /Differences array',
        'indirect numeric /Widths entries',
    ],
    'indirect_encoding_decoded' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'indirect_width_entries_resolved' => !str_contains($plainText, 'Wide Block'),
    'narrow_width_gap_preserved' => !str_contains($plainText, 'ThinText'),
    'raw_source_codes_excluded' => !str_contains($plainText, 'ABCD') && !str_contains($plainText, 'TUVW'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
