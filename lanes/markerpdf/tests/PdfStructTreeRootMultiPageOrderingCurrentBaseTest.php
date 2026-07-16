<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$structTreeRootMultiPageOrderingPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf '
        . '/BodyAlias << /MCID 0 >> BDC 72 704 Td (Repeated section detail) Tj EMC '
        . '/DeckTitle << /MCID 1 >> BDC 72 720 Td (Opening heading) Tj EMC ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Untagged appendix gap) Tj ET';
    $pageThreeContent = 'BT /F1 12 Tf '
        . '/BodyAlias << /MCID 0 >> BDC 72 704 Td (Repeated section detail) Tj EMC '
        . '/TaggedTable << /MCID 1 >> BDC 72 720 Td (Closing table caption) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 /Resources << /Font << /F1 9 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 10 /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 7 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 30 /Contents 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($pageThreeContent) . " >>\nstream\n{$pageThreeContent}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /DeckTitle /H2 /BodyAlias /P /TaggedTable /Table >> /ParentTree 21 0 R /K [32 0 R 31 0 R 52 0 R 51 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Kids [22 0 R 23 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [10 10] /Nums [10 [31 0 R 32 0 R]] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [30 30] /Nums [30 [51 0 R 52 0 R]] >>\nendobj\n"
        . "31 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 0 >>\nendobj\n"
        . "32 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /K 1 >>\nendobj\n"
        . "51 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 0 >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /TaggedTable /P 20 0 R /K 1 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'uses StructTreeRoot K order across ParentTree page branches while preserving untagged page gaps' => static function (
        TestRunner $t
    ) use ($structTreeRootMultiPageOrderingPdf): void {
        $pdf = $structTreeRootMultiPageOrderingPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $tagged = $extractor->extractTaggedContent($pdf);

        $expected = [
            'Opening heading',
            'Repeated section detail',
            'Untagged appendix gap',
            'Closing table caption',
            'Repeated section detail',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(2, substr_count($plainText, 'Repeated section detail'));
        $t->same(['Opening heading', 'Repeated section detail', 'Closing table caption', 'Repeated section detail'], array_column($tagged, 'text'));
        $t->same([1, 1, 3, 3], array_column($tagged, 'page_number'));
        $t->same([1, 0, 1, 0], array_column($tagged, 'mcid'));
        $t->same(['H2', 'P', 'Table', 'P'], array_column($tagged, 'role'));
        $t->same(['DeckTitle', 'BodyAlias', 'TaggedTable', 'BodyAlias'], array_column($tagged, 'raw_role'));
        $t->true(!str_contains($plainText, "Repeated section detail\nOpening heading"));
        $t->true(!str_contains($plainText, "Repeated section detail\nClosing table caption"));
    },
];
