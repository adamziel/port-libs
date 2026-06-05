<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validThinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (valid d1 bbox charproc text leak) Tj ET\n";
$malformedStringBBoxCharProc = "250 0 (bad bbox operand) 0 250 700 d1\n"
    . "BT /Fghost 9 Tf (malformed string bbox charproc text leak) Tj ET\n";
$malformedDictionaryBBoxCharProc = "250 0 0 0 << /Bad 700 >> 700 d1\n"
    . "BT /Fghost 9 Tf (malformed dictionary bbox charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 96 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 118 704 Tm <5758595A> Tj '
    . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
    . '1 0 0 1 130 688 Tm <656667> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /T.valid /h.valid /i.valid /n.valid '
    . '/T.valid /e.valid /x.valid /t.valid '
    . '84 /B.string /a.string /d.string /B.string /B.string /o.string /x.string '
    . '97 /D.dict /i.dict /c.dict /t.dict /G.dict /a.dict /p.dict] >>';
$charProcs = '<< /T.valid 3 0 R /h.valid 3 0 R /i.valid 3 0 R /n.valid 3 0 R '
    . '/e.valid 3 0 R /x.valid 3 0 R /t.valid 3 0 R '
    . '/B.string 4 0 R /a.string 4 0 R /d.string 4 0 R /o.string 4 0 R /x.string 4 0 R '
    . '/D.dict 5 0 R /i.dict 5 0 R /c.dict 5 0 R /t.dict 5 0 R '
    . '/G.dict 5 0 R /a.dict 5 0 R /p.dict 5 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 40, 1000));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3D1BBoxOperandBoundary /BaseFont /T3D1BBoxOperandBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validThinCharProc) . " >>\nstream\n{$validThinCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedStringBBoxCharProc) . " >>\nstream\n{$malformedStringBBoxCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($malformedDictionaryBBoxCharProc) . " >>\nstream\n{$malformedDictionaryBBoxCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3D1BBoxOperandBoundary /Flags 4 /MissingWidth 1000 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-d1-bbox-operand-boundary',
    'font_width_sources' => [
        'valid Type3 d1 wx wy and bbox numeric operands',
        'malformed Type3 d1 string bbox operand rejected before fallback width',
        'malformed Type3 d1 dictionary bbox operand rejected before fallback width',
    ],
    'valid_d1_bbox_metric_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'string_bbox_d1_metric_rejected' => str_contains($plainText, 'BadBBox') && !str_contains($plainText, 'Bad BBox'),
    'dictionary_bbox_d1_metric_rejected' => str_contains($plainText, 'DictGap') && !str_contains($plainText, 'Dict Gap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'bbox charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_d1_bbox_metric_preserved',
    'string_bbox_d1_metric_rejected',
    'dictionary_bbox_d1_metric_rejected',
    'charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 d1 bbox operand boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-d1-bbox-operand-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
