<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\n"
    . "q 2 0 0 2 0 0 cm /Glyph#20Image Do Q\n"
    . "BT /Fghost 9 Tf (wide symbol Type3 CharProc text leak) Tj ET\n";
$thinCharProc = "250 0 d0\n"
    . "q 2 0 0 2 0 0 cm /Glyph#20Image Do Q\n"
    . "BT /Fghost 9 Tf (thin symbol Type3 CharProc text leak) Tj ET\n";
$pageContent = 'BT /Ft3 12 Tf 72 720 Td <616267> Tj ET';
$glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Symbol Type3 Glyph Image Payload Noise) Tj ET';
$glyphCompressed = gzcompress($glyphPayload);
if (!is_string($glyphCompressed)) {
    throw new RuntimeException('Unable to compress Symbol Type3 glyph image payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
    . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3SymbolCharProcBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /SymbolEncoding "
    . "/CharProcs << /alpha 3 0 R /beta 3 0 R /gamma 4 0 R >> "
    . "/Resources << /XObject << /Glyph#20Image 5 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray "
    . "/BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\n"
    . "stream\n{$glyphCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$glyphs = array_values(array_unique(array_filter(array_map(
    static fn (array $entry): ?string => is_string($entry['type3_glyph_name'] ?? null)
        ? $entry['type3_glyph_name']
        : null,
    $review['entries']
))));

$summary = [
    'source' => 'native-pdf-type3-charprocs-symbol-encoding-boundary',
    'decoded_text' => $plainText,
    'symbol_encoding_charprocs_reviewed' => $review['image_xobject_count'] === 3
        && $glyphs === ['alpha', 'beta', 'gamma'],
    'type3_glyphs' => $glyphs,
    'payload_in_visible_text' => str_contains($plainText, 'Symbol Type3 Glyph Image Payload Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($summary['symbol_encoding_charprocs_reviewed'] !== true || $summary['payload_in_visible_text'] !== false) {
    throw new RuntimeException('Symbol-encoded Type3 CharProc boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-type3-charprocs-symbol-encoding-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
