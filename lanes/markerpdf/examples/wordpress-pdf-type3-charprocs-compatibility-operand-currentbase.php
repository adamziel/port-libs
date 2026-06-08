<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid compatibility operand charproc text leak) Tj ET\n";
$malformedCompatibilityCharProc = "(bad outer BX operand) BX /Ignored UnknownCompatibilityOperator EX\n"
    . "1000 0 d0\nBT /Fghost 9 Tf (malformed compatibility operand charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 118 704 Tm <5758595A> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
    . '/W.valid /i.valid /d.valid /e.valid '
    . '84 /B.bad /a.bad /d.bad /G.bad /a.bad /p.bad /Z.bad] >>';
$charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
    . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
    . '/B.bad 4 0 R /a.bad 4 0 R /d.bad 4 0 R '
    . '/G.bad 4 0 R /p.bad 4 0 R /Z.bad 4 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 26, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CompatibilityOperandBoundary "
    . "/BaseFont /T3CompatibilityOperandBoundary /FontBBox [0 0 1000 700] "
    . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 90 "
    . "/Widths [{$fallbackWidths}] /Encoding {$encoding} /CharProcs {$charProcs} "
    . "/FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedCompatibilityCharProc) . " >>\nstream\n{$malformedCompatibilityCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CompatibilityOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-compatibility-operand-boundary',
    'font_width_sources' => [
        'valid Type3 CharProc d0 metric before WordPress text grouping',
        'malformed outer BX operand rejected before late Type3 CharProc d0 metric',
        'declared Type3 Widths fallback used for malformed compatibility-section opener',
    ],
    'valid_charproc_width_preserved' => str_contains($plainText, 'GoodWide') && !str_contains($plainText, 'Good Wide'),
    'outer_bx_operand_metric_rejected' => str_contains($plainText, 'Bad GapZ') && !str_contains($plainText, 'BadGapZ'),
    'compatibility_operator_payload_excluded' => !str_contains($plainText, 'UnknownCompatibilityOperator')
        && !str_contains($plainText, 'bad outer BX operand'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'compatibility operand charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_charproc_width_preserved',
    'outer_bx_operand_metric_rejected',
    'compatibility_operator_payload_excluded',
    'charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs compatibility operand boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-compatibility-operand-currentbase '
    . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
