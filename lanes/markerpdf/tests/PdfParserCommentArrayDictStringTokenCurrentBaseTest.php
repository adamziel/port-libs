<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserCommentArrayDictStringTokenCurrentBasePdf = static function (): string {
    $content = '/OC/HiddenLayer BDC BT /F1 12 Tf 72 720 Td (Hidden dictionary comment leak) Tj ET EMC '
        . '/OC/VisibleLayer BDC BT /F1 12 Tf 72 700 Td (Visible 100% literal layer) Tj ET EMC';

    return "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Type /Catalog /Pages 2 0 R /OCProperties << % fake dictionary close >> /ON [20 0 R]\n"
        . " /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [ % 20 0 R ] fake hidden ref\n"
        . " 21 0 R] /Order [20 0 R 21 0 R] >> >> /Outlines 6 0 R >>\n"
        . "endobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /HiddenLayer 20 0 R /VisibleLayer 21 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Outlines /First 7 0 R /Count 1 >>\nendobj\n"
        . "7 0 obj\n<< /Title 40 0 R /Dest [ % 99 0 R ] stale destination\n 3 0 R /XYZ null null null ] >>\nendobj\n"
        . "20 0 obj\n<< /Type /OCG /Name (Hidden 100% review layer) >>\nendobj\n"
        . "21 0 obj\n<< /Type /OCG /Name (Visible 100% import layer) >>\nendobj\n"
        . "40 0 obj\n% /Title (Fake comment title)\n(Visible 100% outline)\nendobj\n"
        . "%%EOF";
};

return [
    'skips PDF comments across arrays dictionaries and indirect string tokens before current-base text extraction' => static function (TestRunner $t) use ($parserCommentArrayDictStringTokenCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserCommentArrayDictStringTokenCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $metadata = $extractor->extractOutlineMetadata($pdf);

        $t->same(['Visible 100% literal layer'], $extractor->extractTextLines($pdf));
        $t->same(['Visible 100% literal layer'], $extractor->extractTextRuns($pdf));
        $t->same('Visible 100% literal layer', $plainText);
        $t->same("Visible 100% literal layer\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $metadata['pages']);
        $t->same([
            [
                'title' => 'Visible 100% outline',
                'level' => 1,
                'page' => 0,
            ],
        ], $metadata['pdf_toc']);
        $t->true(!str_contains($plainText, 'Hidden dictionary comment leak'));
        $t->true(!str_contains($plainText, 'Hidden 100% review layer'));
        $t->true(!str_contains($plainText, 'Fake comment title'));
        $t->true(!str_contains($plainText, '99 0 R'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
