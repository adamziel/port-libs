<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageFormXObjectStructTreeClipCurrentBasePdf = static function (): string {
    $pageContent = 'q 72 650 240 50 re W n q /FmStruct Do Q Q '
        . 'BT /F1 12 Tf 72 620 Td (Unmarked page tail ignored by StructTree replay) Tj ET';
    $formContent = 'BT /F1 12 Tf '
        . '/P << /MCID 0 >> BDC 0 24 Td (Visible form body) Tj EMC '
        . '/P << /MCID 1 /ActualText (Hidden replacement leak) >> BDC 0 100 Td (Hidden clipped form body) Tj EMC '
        . 'ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> /XObject << /FmStruct 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 140] /Matrix [1 0 0 1 72 650] /Resources << /Font << /F1 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /K [22 0 R 21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /P /Pg 3 0 R /K 0 >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /P /Pg 3 0 R /K 1 >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves page clipping when StructTree reorders invoked Form XObject MCIDs' => static function (TestRunner $t) use ($pageFormXObjectStructTreeClipCurrentBasePdf): void {
        $pdf = $pageFormXObjectStructTreeClipCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = ['Visible form body'];
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $pages[0]['blocks'] ?? []
        );
        $tagged = $extractor->extractTaggedContent($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same([0], array_column($tagged, 'mcid'));
        $t->same(['P'], array_column($tagged, 'role'));
        $t->same($expected, array_column($tagged, 'text'));
        $t->true(!str_contains($plainText, 'Hidden replacement leak'));
        $t->true(!str_contains($plainText, 'Hidden clipped form body'));
        $t->true(!str_contains($plainText, 'Unmarked page tail ignored by StructTree replay'));
    },
];
