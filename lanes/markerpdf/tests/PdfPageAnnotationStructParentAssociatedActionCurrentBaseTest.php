<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationStructParentAssociatedActionPayload = '<wp-export><post id="annotation-action"/></wp-export>';
$pageAnnotationStructParentAssociatedActionChecksum = strtoupper(hash('md5', $pageAnnotationStructParentAssociatedActionPayload));
$pageAnnotationStructParentAssociatedActionPdf = static function () use (
    $pageAnnotationStructParentAssociatedActionPayload,
    $pageAnnotationStructParentAssociatedActionChecksum
): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review attached action) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Local action target page) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 16 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 17 /Rect [72 700 230 724] /Contents (Action attachment review note) /A << /S /URI /URI (https://example.com/attachment-action) /Next 12 0 R >> /AA << /E << /S /JavaScript /JS (actionHoverReview\\(\\)) >> /U << /S /GoToR /F 20 0 R /D (remote-action-target) >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (annotation-action-source.xml) /Desc (Annotation action source file) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationStructParentAssociatedActionPayload) . " /CheckSum <{$pageAnnotationStructParentAssociatedActionChecksum}> /ModDate (D:20260602205800Z) >> /Length " . strlen($pageAnnotationStructParentAssociatedActionPayload) . " >>\nstream\n{$pageAnnotationStructParentAssociatedActionPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D (local-action-target) >>\nendobj\n"
        . "13 0 obj\n<< /Names [(local-action-target) 14 0 R] >>\nendobj\n"
        . "14 0 obj\n[4 0 R /FitH 720]\nendobj\n"
        . "15 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (related-action.pdf) >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewActionLink /Link >> /ParentTree 31 0 R /K [40 0 R 42 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [17 40 0 R 99 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /ReviewActionLink /Pg 3 0 R /T (Annotation action structure) /Alt (Annotation action alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewActionLink /Pg 3 0 R /T (Detached action structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$pageAnnotationStructParentAssociatedActionPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 230.0, 724.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 230.0, 724.0],
                'spans' => [[
                    'text' => 'Review attached action',
                    'bbox' => [72.0, 700.0, 230.0, 724.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'carries annotation StructParent associated files onto current action review rows' => static function (TestRunner $t) use (
        $pageAnnotationStructParentAssociatedActionPdf,
        $pageAnnotationStructParentAssociatedActionPages,
        $pageAnnotationStructParentAssociatedActionPayload,
        $pageAnnotationStructParentAssociatedActionChecksum
    ): void {
        $pdf = $pageAnnotationStructParentAssociatedActionPdf();
        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $linkPages = $linkExtractor->extractPageLinks($pdf);
        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotationStructParentAssociatedActionPages(), $pdf);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(6, $annotation['annotation_object']);
        $t->same(17, $annotation['struct_parent']);
        $t->same(40, $annotation['structure_parent']['struct_object']);
        $t->same('Link', $annotation['structure_parent']['role']);
        $t->same(1, $annotation['structure_parent']['associated_file_count']);

        $actions = $annotation['actions'];
        $t->same(['review-uri', 'local-destination'], array_column($actions, 'safety'));
        $t->same([17, 17], array_column($actions, 'annotation_struct_parent'));
        $t->same([6, 6], array_column($actions, 'source_annotation_object'));
        $t->same([1, 1], array_column($actions, 'annotation_associated_file_count'));
        $t->same('Annotation action structure', $actions[0]['annotation_structure_parent']['title']);
        $t->same('annotation-action-source.xml', $actions[0]['annotation_associated_files'][0]['filename']);
        $t->same('Source', $actions[0]['annotation_associated_files'][0]['relationship']);
        $t->same('text/xml', $actions[0]['annotation_associated_files'][0]['mime_type']);
        $t->same(hash('sha256', $pageAnnotationStructParentAssociatedActionPayload), $actions[0]['annotation_associated_files'][0]['content_sha256']);
        $t->same(strtolower($pageAnnotationStructParentAssociatedActionChecksum), $actions[0]['annotation_associated_files'][0]['checksum']);
        $t->same(hash('md5', $pageAnnotationStructParentAssociatedActionPayload), $actions[0]['annotation_associated_files'][0]['computed_checksum']);
        $t->same(true, $actions[0]['annotation_associated_files'][0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $actions[0]['annotation_associated_files'][0]));
        $t->same('local-action-target', $actions[1]['destination']);
        $t->same(1, $actions[1]['destination_page']);
        $t->same(true, $actions[1]['chained']);

        $additionalActions = $annotation['additional_actions'];
        $t->same(['E', 'U'], array_column($additionalActions, 'event'));
        $t->same(['blocked-javascript', 'remote-document-review'], array_column($additionalActions, 'safety'));
        $t->same([17, 17], array_column($additionalActions, 'annotation_struct_parent'));
        $t->same([1, 1], array_column($additionalActions, 'annotation_associated_file_count'));
        $t->same('related-action.pdf', $additionalActions[1]['file']);
        $t->same('remote-action-target', $additionalActions[1]['destination']);

        $link = $linkPages[0]['links'][0];
        $t->same(17, $link['struct_parent']);
        $t->same('annotation-action-source.xml', $link['actions'][0]['annotation_associated_files'][0]['filename']);
        $t->same(17, $link['additional_actions'][0]['annotation_struct_parent']);
        $t->same(false, $link['executes_on_import']);

        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same(17, $span['link_struct_parent']);
        $t->same('annotation-action-source.xml', $span['link_actions_review'][0]['annotation_associated_files'][0]['filename']);
        $t->same(17, $span['link_additional_actions_review'][1]['annotation_struct_parent']);
        $t->same('[Review attached action](https://example.com/attachment-action)', $blocks[0]['text']);

        $encoded = json_encode($annotationPages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached action structure'));
        $t->contains('Review attached action', $plainText);
        $t->contains('Local action target page', $plainText);
        $t->same(false, str_contains($plainText, 'Action attachment review note'));
        $t->same(false, str_contains($plainText, 'Annotation action structure'));
        $t->same(false, str_contains($plainText, 'Annotation action alternate review'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'annotation-action-source.xml'));
        $t->same(false, str_contains($plainText, 'https://example.com/attachment-action'));
        $t->same(false, str_contains($plainText, 'actionHoverReview'));
        $t->same(false, str_contains($plainText, 'related-action.pdf'));
    },
];
