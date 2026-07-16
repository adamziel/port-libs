<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /Fsubset 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj 1 0 0 1 118 720 Tm <4546474849> Tj ET '
    . 'BT /Fthin 12 Tf 1 0 0 1 72 704 Tm <54555657> Tj 1 0 0 1 98 704 Tm <58595A5B> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsubset 2 0 R /Fthin 7 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+WideSubset /Encoding 6 0 R /FirstChar 3 0 R /LastChar 4 0 R /Widths [1000 1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "3 0 obj\n65\nendobj\n"
    . "4 0 obj\n73\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [65 /W /i /d /e /B /l /o /c /k 84 /T /h /i /n /T /e /x /t] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ThinSubset /Encoding 6 0 R /FirstChar 8 0 R /LastChar 9 0 R /Widths 10 0 R >>\nendobj\n"
    . "8 0 obj\n84\nendobj\n"
    . "9 0 obj\n91\nendobj\n"
    . "10 0 obj\n[250 250 250 250 250 250 250 250]\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-simple-font-indirect-firstchar-widths ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-simple-font-indirect-firstchar-width-boundary',
    'font_width_sources' => ['indirect /FirstChar', 'direct /Widths', 'indirect /Widths', 'subset /Encoding /Differences'],
    'indirect_firstchar_widths_resolved' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'wide_subset_not_split' => !str_contains($plainText, 'Wide Block'),
    'thin_subset_gap_preserved' => !str_contains($plainText, 'ThinText'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
