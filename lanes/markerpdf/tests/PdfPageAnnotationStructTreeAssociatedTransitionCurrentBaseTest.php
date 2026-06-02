<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationStructTreeAssociatedTransitionPayload = '<wp-export><post id="annotation-transition"/></wp-export>';
$pageAnnotationStructTreeAssociatedTransitionChecksum = strtoupper(hash('md5', $pageAnnotationStructTreeAssociatedTransitionPayload));
$pageAnnotationStructTreeAssociatedTransitionPdf = static function () use (
    $pageAnnotationStructTreeAssociatedTransitionPayload,
    $pageAnnotationStructTreeAssociatedTransitionChecksum
): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Transition annotated link) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Transition target page visible) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (Source ) /S /D /St 2 >> 1 << /P (Target ) /S /D /St 7 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Dur 4.5 /Trans 16 0 R /AA << /O 17 0 R /C 18 0 R >> /Contents 19 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 22 /Rect [72 700 240 724] /Contents (Transition action review note) /A << /S /GoTo /D (transition-target) /Next 12 0 R >> /AA << /U << /S /GoTo /D [4 0 R /FitH 620] >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (annotation-transition-source.xml) /Desc (Annotation transition source file) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationStructTreeAssociatedTransitionPayload) . " /CheckSum <{$pageAnnotationStructTreeAssociatedTransitionChecksum}> /ModDate (D:20260602220500Z) >> /Length " . strlen($pageAnnotationStructTreeAssociatedTransitionPayload) . " >>\nstream\n{$pageAnnotationStructTreeAssociatedTransitionPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/transition-followup) >>\nendobj\n"
        . "13 0 obj\n<< /Names [(transition-target) 14 0 R] >>\nendobj\n"
        . "14 0 obj\n[4 0 R /XYZ 72 680 0]\nendobj\n"
        . "15 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "16 0 obj\n<< /S /Dissolve /D .75 >>\nendobj\n"
        . "17 0 obj\n<< /S /URI /URI (https://example.com/page-open-transition-review) >>\nendobj\n"
        . "18 0 obj\n<< /S /JavaScript /JS (targetCloseReview\\(\\)) >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /TransitionLink /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [22 40 0 R 99 41 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /TransitionLink /Pg 3 0 R /T (Annotation transition structure) /Alt (Annotation transition alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /TransitionLink /Pg 3 0 R /T (Detached transition structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'carries target page transition context on structured associated annotation action rows' => static function (TestRunner $t) use (
        $pageAnnotationStructTreeAssociatedTransitionPdf,
        $pageAnnotationStructTreeAssociatedTransitionPayload,
        $pageAnnotationStructTreeAssociatedTransitionChecksum
    ): void {
        $pdf = $pageAnnotationStructTreeAssociatedTransitionPdf();
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $annotation = $pages[0]['annotations'][0];
        $t->same(6, $annotation['annotation_object']);
        $t->same(22, $annotation['struct_parent']);
        $t->same(40, $annotation['structure_parent']['struct_object']);
        $t->same('TransitionLink', $annotation['structure_parent']['raw_role']);
        $t->same('Link', $annotation['structure_parent']['role']);
        $t->same('Annotation transition structure', $annotation['structure_parent']['title']);
        $t->same('Annotation transition alternate review', $annotation['structure_parent']['alternate_text']);
        $t->same([6], $annotation['structure_parent']['annotation_objects']);
        $t->same(true, $annotation['structure_parent']['current_annotation_object_ref_matched']);

        $file = $annotation['structure_parent']['associated_files'][0];
        $t->same(1, $annotation['structure_parent']['associated_file_count']);
        $t->same('annotation-transition-source.xml', $file['filename']);
        $t->same('Source', $file['relationship']);
        $t->same('text/xml', $file['mime_type']);
        $t->same(hash('sha256', $pageAnnotationStructTreeAssociatedTransitionPayload), $file['content_sha256']);
        $t->same(strtolower($pageAnnotationStructTreeAssociatedTransitionChecksum), $file['checksum']);
        $t->same(hash('md5', $pageAnnotationStructTreeAssociatedTransitionPayload), $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(false, array_key_exists('content', $file));

        $actions = $annotation['actions'];
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        $t->same('transition-target', $actions[0]['destination']);
        $t->same(1, $actions[0]['destination_page']);
        $t->same('Target 7', $actions[0]['destination_page_label']);
        $t->same('XYZ', $actions[0]['view_mode']);
        $t->same(['left' => 72.0, 'top' => 680.0, 'zoom' => null], $actions[0]['view_parameters']);
        $t->same(4.5, $actions[0]['target_display_duration']);
        $t->same('Dissolve', $actions[0]['target_page_transition']['style']);
        $t->same(0.75, $actions[0]['target_page_transition']['duration']);
        $t->same(['page_open', 'page_close'], array_column($actions[0]['target_page_actions'], 'event_label'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($actions[0]['target_page_actions'], 'safety'));
        $t->same([false, false], array_column($actions[0]['target_page_actions'], 'executes_on_import'));
        $t->same(22, $actions[0]['annotation_struct_parent']);
        $t->same(6, $actions[0]['source_annotation_object']);
        $t->same(1, $actions[0]['annotation_associated_file_count']);
        $t->same('annotation-transition-source.xml', $actions[0]['annotation_associated_files'][0]['filename']);
        $t->same('https://example.com/transition-followup', $actions[1]['uri']);
        $t->same(22, $actions[1]['annotation_struct_parent']);
        $t->same(false, array_key_exists('target_page_transition', $actions[1]));

        $additional = $annotation['additional_actions'];
        $t->same(['U'], array_column($additional, 'event'));
        $t->same(['local-destination'], array_column($additional, 'safety'));
        $t->same(1, $additional[0]['destination_page']);
        $t->same('Target 7', $additional[0]['destination_page_label']);
        $t->same('FitH', $additional[0]['view_mode']);
        $t->same(['top' => 620.0], $additional[0]['view_parameters']);
        $t->same('Dissolve', $additional[0]['target_page_transition']['style']);
        $t->same(22, $additional[0]['annotation_struct_parent']);
        $t->same('annotation-transition-source.xml', $additional[0]['annotation_associated_files'][0]['filename']);

        $encoded = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached transition structure'));
        $t->contains('Transition annotated link', $plainText);
        $t->contains('Transition target page visible', $plainText);
        $t->same(false, str_contains($plainText, 'Transition action review note'));
        $t->same(false, str_contains($plainText, 'Annotation transition structure'));
        $t->same(false, str_contains($plainText, 'Annotation transition alternate review'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'annotation-transition-source.xml'));
        $t->same(false, str_contains($plainText, 'https://example.com/transition-followup'));
        $t->same(false, str_contains($plainText, 'page-open-transition-review'));
        $t->same(false, str_contains($plainText, 'targetCloseReview'));
    },
];
