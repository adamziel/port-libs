<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsInlineImageBoundaryCurrentBasePdf = static function (): string {
    $validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid inline boundary charproc text leak) Tj ET\n";
    $inlineImageBeforeMetricCharProc = "q BI /W 1 /H 1 /BPC 1 /CS /DeviceGray ID \x00 EI Q\n"
        . "1000 0 d0\nBT /Fghost 9 Tf (inline image charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 118 704 Tm <58595A5B5C> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.good /i.good /d.good /e.good '
        . '/B.good /l.good /o.good /c.good /k.good '
        . '84 /L.inline /a.inline /t.inline /e.inline /I.inline /m.inline /a.inline /g.inline /e.inline] >>';
    $charProcs = '<< /W.good 3 0 R /i.good 3 0 R /d.good 3 0 R /e.good 3 0 R '
        . '/B.good 3 0 R /l.good 3 0 R /o.good 3 0 R /c.good 3 0 R /k.good 3 0 R '
        . '/L.inline 4 0 R /a.inline 4 0 R /t.inline 4 0 R /e.inline 4 0 R '
        . '/I.inline 4 0 R /m.inline 4 0 R /g.inline 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3InlineImageMetricBoundary /BaseFont /T3InlineImageMetricBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($inlineImageBeforeMetricCharProc) . " >>\nstream\n{$inlineImageBeforeMetricCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3InlineImageMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects Type3 CharProc metric operators after inline-image paint before WordPress text grouping on current base' => static function (TestRunner $t) use ($type3CharProcsInlineImageBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsInlineImageBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Late Image'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Late', 'Image'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nLate Image", $plainText);
        $t->same("WideBlock\nLate Image\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'LateImage'));
        $t->true(!str_contains($plainText, 'valid inline boundary charproc text leak'));
        $t->true(!str_contains($plainText, 'inline image charproc text leak'));
        $t->true(!str_contains($plainText, 'DeviceGray'));
    },
];
