<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validCharProc = "q 1 0 0 1 0 0 cm 0 0 m 1000 0 l h Q\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (valid premetric operand charproc text leak) Tj ET\n";
$malformedPathCharProc = "(bad path operand) 0 0 m\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (malformed premetric operand charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <4142434445> Tj '
    . '1 0 0 1 118 720 Tm <46474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 118 704 Tm <5758595A> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /V.validsetup /a.validsetup /l.validsetup /i.validsetup /d.validsetup '
    . '/P.validsetup /a.validsetup /t.validsetup /h.validsetup '
    . '84 /B.badsetup /a.badsetup /d.badsetup /P.badsetup /a.badsetup /t.badsetup /h.badsetup] >>';
$charProcs = '<< /V.validsetup 3 0 R /a.validsetup 3 0 R /l.validsetup 3 0 R /i.validsetup 3 0 R '
    . '/d.validsetup 3 0 R /P.validsetup 3 0 R /t.validsetup 3 0 R /h.validsetup 3 0 R '
    . '/B.badsetup 4 0 R /a.badsetup 4 0 R /d.badsetup 4 0 R /P.badsetup 4 0 R '
    . '/t.badsetup 4 0 R /h.badsetup 4 0 R >>';
$widthValues = array_fill(0, 26, 250);
$fallbackWidths = implode(' ', $widthValues);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PreMetricOperandBoundary /BaseFont /T3PreMetricOperandBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedPathCharProc) . " >>\nstream\n{$malformedPathCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PreMetricOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-premetric-operand-boundary',
    'font_width_sources' => [
        'valid Type3 CharProc cm/path setup before d0 width',
        'malformed Type3 CharProc path operands rejected before d0 width',
        'fallback Type3 Widths preserve WordPress text grouping after malformed glyph program',
    ],
    'valid_premetric_setup_width_preserved' => str_contains($plainText, 'ValidPath') && !str_contains($plainText, 'Valid Path'),
    'malformed_premetric_path_operands_rejected' => str_contains($plainText, 'Bad Path') && !str_contains($plainText, 'BadPath'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'premetric operand charproc text leak')
        && !str_contains($plainText, 'bad path operand'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_premetric_setup_width_preserved',
    'malformed_premetric_path_operands_rejected',
    'charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 pre-metric operand boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-premetric-operand-boundary-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
