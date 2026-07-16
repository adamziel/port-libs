<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentsResourcesTransitionLabelPdf = static function (): string {
    $pageOneContent = 'BT /Fpage 12 Tf '
        . '/DeckBody << /MCID 1 >> BDC 72 700 Td (Deck body follows heading) Tj EMC '
        . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Deck title first) Tj EMC ET';
    $pageTwoContent = 'BT /Fparent 12 Tf /DeckNote << /MCID 0 >> BDC 72 720 Td (Inherited resource body) Tj EMC ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 40 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 4 /Resources << /Font << /Fpage 8 0 R >> /XObject << /Hero 9 0 R >> /Properties << /PActual 11 0 R >> >> /Contents 5 0 R /Dur 5 /Trans 15 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Contents 6 0 R /Dur 8 /Trans << /S /Dissolve /D 1.25 >> >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 120 40] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "11 0 obj\n<< /ActualText (Deck title actual review text) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "15 0 obj\n<< /S /Split /D 0.5 /Dm /H /M /O /Di 0 >>\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /DeckTitle /H1 /DeckBody /P /DeckNote /P >> /ParentTree 21 0 R /K [22 0 R 23 0 R 24 0 R 25 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Nums [4 [22 0 R 23 0 R] 5 [24 0 R null 25 0 R]] >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /Pg 3 0 R /K 0 >>\nendobj\n"
        . "23 0 obj\n<< /Type /StructElem /S /DeckBody /P 20 0 R /Pg 3 0 R /K 1 >>\nendobj\n"
        . "24 0 obj\n<< /Type /StructElem /S /DeckNote /P 20 0 R /Pg 4 0 R /K 0 >>\nendobj\n"
        . "25 0 obj\n<< /Type /StructElem /S /DeckBody /P 20 0 R /Pg 4 0 R /K 2 >>\nendobj\n"
        . "40 0 obj\n<< /Font << /Fparent 12 0 R >> /ColorSpace << /CS1 /DeviceRGB >> /Properties << /ParentActual 41 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Alt (Parent resource alt review text) >>\nendobj\n"
        . "50 0 obj\n<< /Nums [0 << /P (deck-) /S /D /St 7 >> 1 << /P (appendix-) /S /D /St 2 >>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'reviews page StructParents resources transitions and current labels without changing visible text' => static function (TestRunner $t) use ($pageStructParentsResourcesTransitionLabelPdf): void {
        $pdf = $pageStructParentsResourcesTransitionLabelPdf();
        $extractor = new PdfPagePropertyExtractor();
        $boundaries = $extractor->extractPageBoundaryMetadata($pdf);
        $textExtractor = new PdfTextExtractor();

        $t->same(2, count($boundaries));
        $t->same(['deck-7', 'appendix-2'], array_column($boundaries, 'page_label'));
        $t->same([4, 5], array_column($boundaries, 'struct_parents'));

        $first = $boundaries[0];
        $t->same(0, $first['pnum']);
        $t->same(1, $first['page_number']);
        $t->same(3, $first['page_object']);
        $t->same(4, $first['parent_tree']['key']);
        $t->same([0, 1], $first['parent_tree']['mcids']);
        $t->same(['DeckTitle', 'DeckBody'], array_column($first['parent_tree']['entries'], 'raw_role'));
        $t->same(['H1', 'P'], array_column($first['parent_tree']['entries'], 'role'));
        $t->same([22, 23], array_column($first['parent_tree']['entries'], 'struct_object'));
        $t->same(false, $first['resources']['inherited']);
        $t->same(3, $first['resources']['resource_owner_object']);
        $t->same(null, $first['resources']['resource_object']);
        $t->same(['Font', 'XObject', 'Properties'], $first['resources']['categories']);
        $t->same(['Fpage'], $first['resources']['font_names']);
        $t->same(['Hero'], $first['resources']['xobject_names']);
        $t->same(['PActual'], $first['resources']['properties_names']);
        $t->same('Split', $first['page_presentation']['transition']['style']);
        $t->same(0.5, $first['page_presentation']['transition']['duration']);
        $t->same('H', $first['page_presentation']['transition']['dimension']);
        $t->same('O', $first['page_presentation']['transition']['motion']);
        $t->same(5.0, $first['page_presentation']['display_duration']);

        $second = $boundaries[1];
        $t->same(1, $second['pnum']);
        $t->same(2, $second['page_number']);
        $t->same(4, $second['page_object']);
        $t->same(5, $second['parent_tree']['key']);
        $t->same([0, 2], $second['parent_tree']['mcids']);
        $t->same(['DeckNote', 'DeckBody'], array_column($second['parent_tree']['entries'], 'raw_role'));
        $t->same(['P', 'P'], array_column($second['parent_tree']['entries'], 'role'));
        $t->same([24, 25], array_column($second['parent_tree']['entries'], 'struct_object'));
        $t->same(true, $second['resources']['inherited']);
        $t->same(2, $second['resources']['resource_owner_object']);
        $t->same(40, $second['resources']['resource_object']);
        $t->same(['Font', 'ColorSpace', 'Properties'], $second['resources']['categories']);
        $t->same(['Fparent'], $second['resources']['font_names']);
        $t->same(['CS1'], $second['resources']['color_space_names']);
        $t->same(['ParentActual'], $second['resources']['properties_names']);
        $t->same('Dissolve', $second['page_presentation']['transition']['style']);
        $t->same(1.25, $second['page_presentation']['transition']['duration']);
        $t->same(8.0, $second['page_presentation']['display_duration']);

        $t->same(['Deck title first', 'Deck body follows heading', 'Inherited resource body'], $textExtractor->extractTextLines($pdf));
        $plainText = $textExtractor->extractPlainText($pdf);
        $t->contains('Deck title first', $plainText);
        $t->contains('Inherited resource body', $plainText);
        $t->same(false, str_contains($plainText, 'deck-7'));
        $t->same(false, str_contains($plainText, 'appendix-2'));
        $t->same(false, str_contains($plainText, 'Split'));
        $t->same(false, str_contains($plainText, 'Dissolve'));
        $t->same(false, str_contains($plainText, 'Parent resource alt review text'));
        $t->same(false, str_contains($plainText, 'Deck title actual review text'));
    },
];
