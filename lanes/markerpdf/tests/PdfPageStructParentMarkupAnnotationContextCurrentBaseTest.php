<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentMarkupAnnotationContextPdf = static function (): string {
    $content = 'BT /F1 12 Tf /Body << /MCID 0 >> BDC 72 720 Td (Visible contextual span) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (ctx-) /S /D /St 3 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 9 /Contents 5 0 R /Annots [7 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /StructParent 20 /Rect [72 700 210 720] /QuadPoints [72 720 210 720 72 700 210 700] /Contents (Private markup review note) /T (Import Reviewer) /Subj (Context highlight) /NM (ctx-highlight-1) /C [1 0.85 0] >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Body /P /ReviewMarkup /Span >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [9 [40 0 R] 20 41 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Visible body structure) /K 0 >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /ReviewMarkup /Pg 3 0 R /T (Span review structure) /Alt (Accessible span review) /ActualText (Actual span review) /ID (span-review-id) /C [/qa /highlight] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$pageStructParentMarkupAnnotationContextPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 260.0, 720.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 260.0, 720.0],
                'spans' => [
                    ['text' => 'Visible contextual span', 'bbox' => [72.0, 700.0, 210.0, 720.0], 'font' => 'Helvetica'],
                    ['text' => 'outside', 'bbox' => [220.0, 700.0, 260.0, 720.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'carries page StructParents and annotation StructParent context onto supplied markup review spans' => static function (TestRunner $t) use (
        $pageStructParentMarkupAnnotationContextPdf,
        $pageStructParentMarkupAnnotationContextPages
    ): void {
        $pdf = $pageStructParentMarkupAnnotationContextPdf();
        $extractor = new PdfMarkupAnnotationExtractor();
        $pages = $extractor->applyMarkupsToPages($pageStructParentMarkupAnnotationContextPages(), $pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pages[0]['markup_annotations']));

        $pageMarkup = $pages[0]['markup_annotations'][0];
        $t->same(20, $pageMarkup['struct_parent']);
        $t->same('page_structparent_markup_annotation_context', $pageMarkup['page_structparent_context']['source']);
        $t->same(9, $pageMarkup['page_structparent_context']['struct_parents']);
        $t->same('ctx-3', $pageMarkup['page_structparent_context']['page_label']);
        $t->same([0], $pageMarkup['page_structparent_context']['parent_tree']['mcids']);
        $t->same(['P'], $pageMarkup['page_structparent_context']['parent_tree']['roles']);
        $t->same('page_text_markup_annotation_struct_parent_context', $pageMarkup['structure_parent']['source']);
        $t->same(20, $pageMarkup['structure_parent']['key']);
        $t->same(41, $pageMarkup['structure_parent']['struct_object']);

        $review = $pages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0];
        $t->same('Highlight', $review['subtype']);
        $t->same('Private markup review note', $review['contents']);
        $t->same(20, $review['struct_parent']);
        $t->same('ctx-3', $review['page_structparent_context']['page_label']);
        $t->same(9, $review['page_structparent_context']['struct_parents']);
        $t->same([0], $review['page_structparent_context']['parent_tree']['mcids']);
        $t->same('page_text_markup_annotation_struct_parent_context', $review['structure_parent']['source']);
        $t->same(20, $review['structure_parent']['key']);
        $t->same(7, $review['structure_parent']['annotation_object']);
        $t->same(41, $review['structure_parent']['struct_object']);
        $t->same('ReviewMarkup', $review['structure_parent']['raw_role']);
        $t->same('Span', $review['structure_parent']['role']);
        $t->same(true, $review['structure_parent']['role_mapped']);
        $t->same('Span review structure', $review['structure_parent']['title']);
        $t->same('Accessible span review', $review['structure_parent']['alternate_text']);
        $t->same('Actual span review', $review['structure_parent']['actual_text']);
        $t->same('span-review-id', $review['structure_parent']['id']);
        $t->same(['qa', 'highlight'], $review['structure_parent']['classes']);
        $t->same(true, $review['structure_parent']['current_page_annotation']);
        $t->same(true, $review['structure_parent']['review_only']);
        $t->same(false, $review['structure_parent']['visible_text_source']);
        $t->same([72.0, 700.0, 210.0, 720.0], $review['quad_rect']);
        $t->same(false, isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations']));

        $t->same(['Visible contextual span'], $textExtractor->extractTextLines($pdf));
        $t->contains('Visible contextual span', $plainText);
        $t->same(false, str_contains($plainText, 'Private markup review note'));
        $t->same(false, str_contains($plainText, 'Span review structure'));
        $t->same(false, str_contains($plainText, 'Accessible span review'));
        $t->same(false, str_contains($plainText, 'Actual span review'));
        $t->same(false, str_contains($plainText, 'span-review-id'));
        $t->same(false, str_contains($plainText, 'ctx-3'));
    },
];
