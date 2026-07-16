<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageParentTreeActionAnnotationPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review link Destination jump Hidden stale) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Destination target page) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 31 /Rect [72 700 150 718] /Contents (Review link annotation note) /A << /S /URI /URI (https://example.com/review-link) /Next 12 0 R >> /AA << /U << /S /JavaScript /JS (hoverReview\\(\\)) >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 32 /Rect [160 700 250 718] /A << /S /GoTo /D (dest-review) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 99 /Rect [260 700 340 718] /F 2 /A << /S /URI /URI (https://example.com/stale-hidden) >> >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D (dest-review) >>\nendobj\n"
        . "13 0 obj\n<< /Names [(dest-review) 14 0 R] >>\nendobj\n"
        . "14 0 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Reference /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [31 40 0 R 32 41 0 R 99 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Link /Pg 3 0 R /T (Review action structure) /ActualText (Actual review link text) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /Reference /Pg 3 0 R /Alt (Destination link alt review) /K [<< /Type /OBJR /Obj 8 0 R >>] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /Link /Pg 3 0 R /T (Hidden stale action structure) /K << /Type /OBJR /Obj 9 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$pageParentTreeActionAnnotationPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 340.0, 718.0],
                'spans' => [
                    ['text' => 'Review link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Destination jump', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden stale', 'bbox' => [260.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'carries ParentTree structure review through current page action link annotations' => static function (TestRunner $t) use (
        $pageParentTreeActionAnnotationPdf,
        $pageParentTreeActionAnnotationPages
    ): void {
        $pdf = $pageParentTreeActionAnnotationPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'Hidden action annotations are not promoted as WordPress links.');

        $uri = $links[0]['links'][0];
        $t->same(7, $uri['annotation_object']);
        $t->same('Link', $uri['annotation_subtype']);
        $t->same('https://example.com/review-link', $uri['uri']);
        $t->same(['review-uri', 'local-destination'], array_column($uri['actions'], 'safety'));
        $t->same(['U'], array_column($uri['additional_actions'], 'event'));
        $t->same(['blocked-javascript'], array_column($uri['additional_actions'], 'safety'));
        $t->same(false, $uri['executes_on_import']);
        $t->same(31, $uri['struct_parent']);
        $t->same('annotation_struct_parent_parent_tree', $uri['structure_parent']['source']);
        $t->same(40, $uri['structure_parent']['struct_object']);
        $t->same('Link', $uri['structure_parent']['raw_role']);
        $t->same('Link', $uri['structure_parent']['role']);
        $t->same('Review action structure', $uri['structure_parent']['title']);
        $t->same('Actual review link text', $uri['structure_parent']['actual_text']);
        $t->same([7], $uri['structure_parent']['annotation_objects']);
        $t->same(true, $uri['structure_parent']['current_annotation_object_ref_matched']);
        $t->same(true, $uri['structure_parent']['current_page_annotation']);
        $t->same(true, $uri['structure_parent']['review_only']);
        $t->same(false, $uri['structure_parent']['visible_text_source']);

        $destination = $links[0]['links'][1];
        $t->same(8, $destination['annotation_object']);
        $t->same('GoTo', $destination['action_type']);
        $t->same('local-destination', $destination['safety']);
        $t->same('dest-review', $destination['destination']);
        $t->same(1, $destination['destination_page']);
        $t->same('FitH', $destination['view_mode']);
        $t->same(32, $destination['struct_parent']);
        $t->same(41, $destination['structure_parent']['struct_object']);
        $t->same('Reference', $destination['structure_parent']['raw_role']);
        $t->same('Link', $destination['structure_parent']['role']);
        $t->same(true, $destination['structure_parent']['role_mapped']);
        $t->same('Destination link alt review', $destination['structure_parent']['alternate_text']);
        $t->same([8], $destination['structure_parent']['annotation_objects']);

        $pages = $extractor->applyLinksToPages($pageParentTreeActionAnnotationPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(31, $spans[0]['link_struct_parent']);
        $t->same('Review action structure', $spans[0]['link_structure_parent']['title']);
        $t->same(32, $spans[1]['link_struct_parent']);
        $t->same('Destination link alt review', $spans[1]['link_structure_parent']['alternate_text']);
        $t->same(false, array_key_exists('link_struct_parent', $spans[2]));
        $t->same(false, array_key_exists('link_uri', $spans[2]));
        $t->same(false, array_key_exists('link_destination_page', $spans[2]));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Review link](https://example.com/review-link) Destination jump Hidden stale', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Review link Destination jump Hidden stale', $plainText);
        $t->contains('Destination target page', $plainText);
        $t->same(false, str_contains($plainText, 'Review action structure'));
        $t->same(false, str_contains($plainText, 'Actual review link text'));
        $t->same(false, str_contains($plainText, 'Destination link alt review'));
        $t->same(false, str_contains($plainText, 'Hidden stale action structure'));
        $t->same(false, str_contains($plainText, 'https://example.com/review-link'));
        $t->same(false, str_contains($plainText, 'hoverReview'));
    },
];
