<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationAssociatedStructTreeNotePayload = '<wp-export><post id="annotation-objr-note"/></wp-export>';
$pageAnnotationAssociatedStructTreeLinkPayload = '<wp-export><post id="annotation-objr-link"/></wp-export>';
$pageAnnotationAssociatedStructTreeNoteChecksum = strtoupper(hash('md5', $pageAnnotationAssociatedStructTreeNotePayload));
$pageAnnotationAssociatedStructTreeLinkChecksum = strtoupper(hash('md5', $pageAnnotationAssociatedStructTreeLinkPayload));
$pageAnnotationAssociatedStructTreePdf = static function () use (
    $pageAnnotationAssociatedStructTreeNotePayload,
    $pageAnnotationAssociatedStructTreeLinkPayload,
    $pageAnnotationAssociatedStructTreeNoteChecksum,
    $pageAnnotationAssociatedStructTreeLinkChecksum
): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Visible associated OBJR source) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Visible associated OBJR target) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 15 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (objr-) /S /D /St 4 >> 1 << /P (target-) /S /D /St 8 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 14 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 17 0 R /Dur 2.5 /Trans << /S /Fade /D 0.5 >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 690 260 730] /Contents (OBJR-only note review text) /T (OBJR QA) /NM (objr-note) >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 23 /Rect [72 650 260 675] /Contents (OBJR fallback link review text) /A << /S /GoTo /D (objr-target) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (objr-note-source.xml) /Desc (OBJR note source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationAssociatedStructTreeNotePayload) . " /CheckSum <{$pageAnnotationAssociatedStructTreeNoteChecksum}> /ModDate (D:20260602222500Z) >> /Length " . strlen($pageAnnotationAssociatedStructTreeNotePayload) . " >>\nstream\n{$pageAnnotationAssociatedStructTreeNotePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (objr-link-source.xml) /Desc (OBJR link source export) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationAssociatedStructTreeLinkPayload) . " /CheckSum <{$pageAnnotationAssociatedStructTreeLinkChecksum}> /ModDate (D:20260602222600Z) >> /Length " . strlen($pageAnnotationAssociatedStructTreeLinkPayload) . " >>\nstream\n{$pageAnnotationAssociatedStructTreeLinkPayload}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "15 0 obj\n<< /Names [(objr-target) 16 0 R] >>\nendobj\n"
        . "16 0 obj\n[4 0 R /FitH 700]\nendobj\n"
        . "17 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewNote /Span /ReviewLink /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [99 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (OBJR-only annotation structure) /Alt (OBJR-only alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (OBJR fallback link structure) /ActualText (OBJR fallback actual review) /AF [12 0 R] /K [<< /Type /OBJR /Obj 7 0 R >>] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Detached stale OBJR structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$pageAnnotationAssociatedStructTreePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 650.0, 260.0, 675.0],
            'lines' => [[
                'bbox' => [72.0, 650.0, 260.0, 675.0],
                'spans' => [[
                    'text' => 'Visible associated OBJR source',
                    'bbox' => [72.0, 650.0, 260.0, 675.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'uses StructTree OBJR annotation associations when annotation StructParent rows are missing' => static function (TestRunner $t) use (
        $pageAnnotationAssociatedStructTreePdf,
        $pageAnnotationAssociatedStructTreePages,
        $pageAnnotationAssociatedStructTreeNotePayload,
        $pageAnnotationAssociatedStructTreeLinkPayload,
        $pageAnnotationAssociatedStructTreeNoteChecksum,
        $pageAnnotationAssociatedStructTreeLinkChecksum
    ): void {
        $pdf = $pageAnnotationAssociatedStructTreePdf();
        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $linkPages = $linkExtractor->extractPageLinks($pdf);
        $linkedPages = $linkExtractor->applyLinksToPages($pageAnnotationAssociatedStructTreePages(), $pdf);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($annotationPages));
        $t->same(2, count($annotationPages[0]['annotations']));

        $note = $annotationPages[0]['annotations'][0];
        $t->same(6, $note['annotation_object']);
        $t->same(false, array_key_exists('struct_parent', $note));
        $t->same('annotation_struct_tree_objr', $note['structure_parent']['source']);
        $t->same(40, $note['structure_parent']['struct_object']);
        $t->same('ReviewNote', $note['structure_parent']['raw_role']);
        $t->same('Span', $note['structure_parent']['role']);
        $t->same(true, $note['structure_parent']['role_mapped']);
        $t->same('OBJR-only annotation structure', $note['structure_parent']['title']);
        $t->same('OBJR-only alternate review', $note['structure_parent']['alternate_text']);
        $t->same([6], $note['structure_parent']['annotation_objects']);
        $t->same(true, $note['structure_parent']['current_annotation_object_ref_matched']);
        $t->same(true, $note['structure_parent']['current_page_annotation']);
        $t->same(false, $note['structure_parent']['visible_text_source']);

        $noteFile = $note['structure_parent']['associated_files'][0];
        $t->same(1, $note['structure_parent']['associated_file_count']);
        $t->same('objr-note-source.xml', $noteFile['filename']);
        $t->same('Source', $noteFile['relationship']);
        $t->same('text/xml', $noteFile['mime_type']);
        $t->same(hash('sha256', $pageAnnotationAssociatedStructTreeNotePayload), $noteFile['content_sha256']);
        $t->same(strtolower($pageAnnotationAssociatedStructTreeNoteChecksum), $noteFile['checksum']);
        $t->same(hash('md5', $pageAnnotationAssociatedStructTreeNotePayload), $noteFile['computed_checksum']);
        $t->same(true, $noteFile['checksum_matches']);
        $t->same(false, array_key_exists('content', $noteFile));

        $linkAnnotation = $annotationPages[0]['annotations'][1];
        $t->same(7, $linkAnnotation['annotation_object']);
        $t->same(23, $linkAnnotation['struct_parent']);
        $t->same('annotation_struct_tree_objr_parent_tree_fallback', $linkAnnotation['structure_parent']['source']);
        $t->same(23, $linkAnnotation['structure_parent']['key']);
        $t->same(true, $linkAnnotation['structure_parent']['parent_tree_key_missing']);
        $t->same(41, $linkAnnotation['structure_parent']['struct_object']);
        $t->same('ReviewLink', $linkAnnotation['structure_parent']['raw_role']);
        $t->same('Link', $linkAnnotation['structure_parent']['role']);
        $t->same('OBJR fallback link structure', $linkAnnotation['structure_parent']['title']);
        $t->same('OBJR fallback actual review', $linkAnnotation['structure_parent']['actual_text']);
        $t->same([7], $linkAnnotation['structure_parent']['annotation_objects']);
        $t->same(true, $linkAnnotation['structure_parent']['current_annotation_object_ref_matched']);

        $linkAction = $linkAnnotation['actions'][0];
        $t->same('local-destination', $linkAction['safety']);
        $t->same('objr-target', $linkAction['destination']);
        $t->same(1, $linkAction['destination_page']);
        $t->same('target-8', $linkAction['destination_page_label']);
        $t->same('FitH', $linkAction['view_mode']);
        $t->same(['top' => 700.0], $linkAction['view_parameters']);
        $t->same(2.5, $linkAction['target_display_duration']);
        $t->same('Fade', $linkAction['target_page_transition']['style']);
        $t->same(23, $linkAction['annotation_struct_parent']);
        $t->same(7, $linkAction['source_annotation_object']);
        $t->same('OBJR fallback link structure', $linkAction['annotation_structure_parent']['title']);

        $linkFile = $linkAction['annotation_associated_files'][0];
        $t->same(1, $linkAction['annotation_associated_file_count']);
        $t->same('objr-link-source.xml', $linkFile['filename']);
        $t->same('Source', $linkFile['relationship']);
        $t->same('text/xml', $linkFile['mime_type']);
        $t->same(hash('sha256', $pageAnnotationAssociatedStructTreeLinkPayload), $linkFile['content_sha256']);
        $t->same(strtolower($pageAnnotationAssociatedStructTreeLinkChecksum), $linkFile['checksum']);
        $t->same(hash('md5', $pageAnnotationAssociatedStructTreeLinkPayload), $linkFile['computed_checksum']);
        $t->same(true, $linkFile['checksum_matches']);
        $t->same(false, array_key_exists('content', $linkFile));

        $t->same(1, count($linkPages));
        $promotedLink = $linkPages[0]['links'][0];
        $t->same(23, $promotedLink['struct_parent']);
        $t->same('annotation_struct_tree_objr_parent_tree_fallback', $promotedLink['structure_parent']['source']);
        $t->same('objr-link-source.xml', $promotedLink['actions'][0]['annotation_associated_files'][0]['filename']);

        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same(23, $span['link_struct_parent']);
        $t->same('objr-target', $span['link_destination']);
        $t->same(1, $span['link_destination_page']);
        $t->same('OBJR fallback link structure', $span['link_structure_parent']['title']);
        $t->same('objr-link-source.xml', $span['link_actions_review'][0]['annotation_associated_files'][0]['filename']);
        $t->same('Visible associated OBJR source', $blocks[0]['text']);

        $t->same(1, count($pageReviews));
        $rows = $pageReviews[0]['annotation_structure_parent_rows'];
        $t->same(2, count($rows));
        $t->same([6, 7], array_column($rows, 'annotation_object'));
        $t->same(false, array_key_exists('struct_parent', $rows[0]));
        $t->same(23, $rows[1]['struct_parent']);
        $t->same('OBJR-only annotation structure', $rows[0]['structure_parent']['title']);
        $t->same('OBJR fallback link structure', $rows[1]['structure_parent']['title']);
        $t->same('objr-note-source.xml', $rows[0]['structure_parent']['associated_files'][0]['filename']);
        $t->same('objr-link-source.xml', $rows[1]['actions'][0]['annotation_associated_files'][0]['filename']);

        $encoded = json_encode([$annotationPages, $pageReviews, $linkPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached stale OBJR structure'));
        $t->same(['Visible associated OBJR source', 'Visible associated OBJR target'], $textExtractor->extractTextLines($pdf));
        $t->contains('Visible associated OBJR source', $plainText);
        $t->contains('Visible associated OBJR target', $plainText);
        $t->same(false, str_contains($plainText, 'OBJR-only note review text'));
        $t->same(false, str_contains($plainText, 'OBJR fallback link review text'));
        $t->same(false, str_contains($plainText, 'OBJR-only annotation structure'));
        $t->same(false, str_contains($plainText, 'OBJR fallback link structure'));
        $t->same(false, str_contains($plainText, 'OBJR fallback actual review'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'objr-note-source.xml'));
        $t->same(false, str_contains($plainText, 'objr-link-source.xml'));
    },
];
