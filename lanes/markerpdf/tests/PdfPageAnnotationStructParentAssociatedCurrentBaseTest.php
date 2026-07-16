<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationStructParentAssociatedPayload = '<wp-export><post id="annotation-struct"/></wp-export>';
$pageAnnotationStructParentAssociatedChecksum = strtoupper(hash('md5', $pageAnnotationStructParentAssociatedPayload));
$pageAnnotationStructParentAssociatedPdf = static function () use (
    $pageAnnotationStructParentAssociatedPayload,
    $pageAnnotationStructParentAssociatedChecksum
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Visible page annotation context) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 676 280 724] /Contents (Review note stays metadata) /T (Annotation QA) /NM /struct-note >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 18 /Rect [72 640 260 668] /Contents (Reference link review) /A << /S /URI /URI (https://example.com/struct-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 600 260 628] /Contents (Plain note review only) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (annotation-source.xml) /Desc (Original annotation source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAnnotationStructParentAssociatedPayload) . " /CheckSum <{$pageAnnotationStructParentAssociatedChecksum}> /ModDate (D:20260602192222Z) >> /Length " . strlen($pageAnnotationStructParentAssociatedPayload) . " >>\nstream\n{$pageAnnotationStructParentAssociatedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewNote /P /DocLink /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R 42 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Limits [17 99] /Nums [17 40 0 R 18 41 0 R 99 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Annotation note structure) /Alt (Annotation alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /DocLink /Pg 3 0 R /T (Annotation link structure) /ActualText (Link actual text review) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Detached stale annotation structure) /K << /Type /OBJR /Obj 99 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'associates current page annotations with singular StructParent ParentTree entries and StructElem files' => static function (TestRunner $t) use (
        $pageAnnotationStructParentAssociatedPdf,
        $pageAnnotationStructParentAssociatedPayload,
        $pageAnnotationStructParentAssociatedChecksum
    ): void {
        $pdf = $pageAnnotationStructParentAssociatedPdf();
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same(3, count($page['annotations']));

        $note = $page['annotations'][0];
        $t->same(6, $note['annotation_object']);
        $t->same(17, $note['struct_parent']);
        $t->same('annotation_struct_parent_parent_tree', $note['structure_parent']['source']);
        $t->same(17, $note['structure_parent']['key']);
        $t->same(40, $note['structure_parent']['struct_object']);
        $t->same('ReviewNote', $note['structure_parent']['raw_role']);
        $t->same('P', $note['structure_parent']['role']);
        $t->same(true, $note['structure_parent']['role_mapped']);
        $t->same('Annotation note structure', $note['structure_parent']['title']);
        $t->same('Annotation alternate review', $note['structure_parent']['alternate_text']);
        $t->same([6], $note['structure_parent']['annotation_objects']);
        $t->same(true, $note['structure_parent']['current_annotation_object_ref_matched']);
        $t->same(true, $note['structure_parent']['current_page_annotation']);
        $t->same(false, $note['structure_parent']['visible_text_source']);
        $t->same(true, $note['structure_parent']['review_only']);

        $files = $note['structure_parent']['associated_files'];
        $t->same(1, $note['structure_parent']['associated_file_count']);
        $t->same('structure_element_associated_files', $files[0]['source']);
        $t->same('annotation-source.xml', $files[0]['filename']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(hash('sha256', $pageAnnotationStructParentAssociatedPayload), $files[0]['content_sha256']);
        $t->same(strtolower($pageAnnotationStructParentAssociatedChecksum), $files[0]['checksum']);
        $t->same(hash('md5', $pageAnnotationStructParentAssociatedPayload), $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $files[0]));

        $link = $page['annotations'][1];
        $t->same(7, $link['annotation_object']);
        $t->same(18, $link['struct_parent']);
        $t->same(41, $link['structure_parent']['struct_object']);
        $t->same('DocLink', $link['structure_parent']['raw_role']);
        $t->same('Link', $link['structure_parent']['role']);
        $t->same('Annotation link structure', $link['structure_parent']['title']);
        $t->same('Link actual text review', $link['structure_parent']['actual_text']);
        $t->same([7], $link['structure_parent']['annotation_objects']);
        $t->same(true, $link['structure_parent']['current_annotation_object_ref_matched']);

        $plain = $page['annotations'][2];
        $t->same(8, $plain['annotation_object']);
        $t->same(false, array_key_exists('struct_parent', $plain));
        $t->same(false, array_key_exists('structure_parent', $plain));

        $encoded = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, 'Detached stale annotation structure'));
        $t->contains('Visible page annotation context', $plainText);
        $t->same(false, str_contains($plainText, 'Review note stays metadata'));
        $t->same(false, str_contains($plainText, 'Annotation note structure'));
        $t->same(false, str_contains($plainText, 'Annotation alternate review'));
        $t->same(false, str_contains($plainText, 'Link actual text review'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'annotation-source.xml'));
    },
];
