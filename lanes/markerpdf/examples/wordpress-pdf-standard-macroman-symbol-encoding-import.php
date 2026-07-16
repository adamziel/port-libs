<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /Fstd 12 Tf 1 0 0 1 72 720 Tm <5750277320AE20AF20E1> Tj ET '
    . 'BT /Fmac 12 Tf 1 0 0 1 72 704 Tm <4D6163208E209F20D6> Tj ET '
    . 'BT /Fsym 12 Tf 1 0 0 1 72 688 Tm <616267202B20B3> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fstd 2 0 R /Fmac 3 0 R /Fsym 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /StandardEncoding >>\nendobj\n"
    . "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /MacRomanSubset /Encoding << /BaseEncoding /MacRomanEncoding >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Symbol >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo "<!-- markerpdf-standard-macroman-symbol-encoding-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'StandardEncoding MacRomanEncoding and implicit SymbolEncoding decoding before Gutenberg paragraph rendering',
    'standard_ligature_decoded' => str_contains($lines[0] ?? '', "\u{FB01}"),
    'macroman_accent_decoded' => str_contains($lines[1] ?? '', "\u{00E9}") && str_contains($lines[1] ?? '', "\u{00FC}"),
    'symbol_greek_decoded' => str_contains($lines[2] ?? '', "\u{03B1}\u{03B2}\u{03B3}"),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
