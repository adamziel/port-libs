<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsDictionaryCommentBoundaryPagePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide dictionary comment charproc text leak) Tj ET\n";
    $thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin dictionary comment charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.comment /i.comment /d.comment /e.comment '
        . '/B.comment /l.comment /o.comment /c.comment /k.comment 84 /T.thin /h.thin /i.thin '
        . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
    $commentDecoy = '<< /W.comment 4 0 R /i.comment 4 0 R /d.comment 4 0 R /e.comment 4 0 R '
        . '/B.comment 4 0 R /l.comment 4 0 R /o.comment 4 0 R /c.comment 4 0 R /k.comment 4 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';
    $currentCharProcs = '<< /W.comment 3 0 R /i.comment 3 0 R /d.comment 3 0 R /e.comment 3 0 R '
        . '/B.comment 3 0 R /l.comment 3 0 R /o.comment 3 0 R /c.comment 3 0 R /k.comment 3 0 R '
        . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
        . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDictionaryComment /BaseFont /T3CharProcsDictionaryComment "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding {$encoding} /CharProcs 21 0 R /FontDescriptor 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsDictionaryComment /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "21 0 obj\n% {$commentDecoy}\n{$currentCharProcs}\nendobj\n%%EOF";
};

$type3CharProcsDictionaryCommentBoundaryFallbackPdf = static function (): string {
    $wideCharProc = "650 0 d0\nBT /Fghost 9 Tf (WIDE COMMENT PROGRAM LEAK) Tj ET\n";
    $thinCharProc = "250 0 d1\nBT /Fghost 9 Tf (THIN COMMENT PROGRAM LEAK) Tj ET\n";
    $visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
    $commentDecoy = '<< /A 4 0 R /B 4 0 R /C 4 0 R /D 4 0 R /V 4 0 R >>';
    $currentCharProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /E 4 0 R '
        . '/V 3 0 R /i 3 0 R /s 3 0 R /b 3 0 R /l 3 0 R /e 3 0 R '
        . '/f 3 0 R /a 3 0 R /c 3 0 R /k 3 0 R /o 3 0 R /n 3 0 R /t 3 0 R >>';

    return "%PDF-1.4\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3CharProcsDictionaryCommentFallback "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
        . "21 0 obj\n% {$commentDecoy}\n{$currentCharProcs}\nendobj\n%%EOF";
};

return [
    'ignores leading comments before indirect Type3 CharProcs dictionaries for WordPress text widths on current base' => static function (
        TestRunner $t
    ) use ($type3CharProcsDictionaryCommentBoundaryPagePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryCommentBoundaryPagePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'dictionary comment charproc text leak'));
    },
    'keeps real Type3 CharProc streams private when the dictionary object starts with a PDF comment' => static function (
        TestRunner $t
    ) use ($type3CharProcsDictionaryCommentBoundaryFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $type3CharProcsDictionaryCommentBoundaryFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible fallback content'], $extractor->extractTextLines($pdf));
        $t->same(['Visible fallback content'], $extractor->extractTextRuns($pdf));
        $t->same('Visible fallback content', $plainText);
        $t->same("Visible fallback content\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'WIDE COMMENT PROGRAM LEAK'));
        $t->true(!str_contains($plainText, 'THIN COMMENT PROGRAM LEAK'));
        $t->true(!str_contains($plainText, 'T3CharProcsDictionaryCommentFallback'));
    },
];
