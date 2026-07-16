<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationParentTreeWidgetPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Visible widget structure boundary text) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Parent 20 0 R /Rect [72 676 280 704] /P 3 0 R /F 4 /AS /Yes /A << /S /URI /URI (https://example.com/widget-approval) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Parent 21 0 R /Rect [72 640 280 668] /P 3 0 R /F 4 >>\nendobj\n"
        . "15 0 obj\n<< /Fields [20 0 R 21 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (approval.publish) /Ff 65536 /V /Yes /StructParent 25 /Kids [7 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (stale.choice) /Ff 65536 /V /Off /StructParent 26 /Kids [8 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /FormControl /Form >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Limits [25 26] /Nums [25 40 0 R 26 41 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /FormControl /Pg 3 0 R /T (Publish approval widget) /Alt (Approval button review) /ActualText (Approval actual text review) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /FormControl /Pg 3 0 R /T (Stale field-only structure) /ActualText (Stale field text review) /K << /Type /OBJR /Obj 21 0 R >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'resolves widget ParentTree metadata from parent field StructParent only when OBJR matches the current page widget' => static function (TestRunner $t) use ($pageAnnotationParentTreeWidgetPdf): void {
        $pdf = $pageAnnotationParentTreeWidgetPdf();
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same(2, count($page['annotations']));

        $widget = $page['annotations'][0];
        $t->same('Widget', $widget['subtype']);
        $t->same(7, $widget['annotation_object']);
        $t->same(25, $widget['struct_parent']);
        $t->same('widget_parent_field_struct_parent', $widget['struct_parent_source']);
        $t->same(20, $widget['struct_parent_field_object']);
        $t->same([20], $widget['struct_parent_field_chain']);
        $t->same('approval.publish', $widget['widget']['field_name']);
        $t->same('Btn', $widget['widget']['field_type']);
        $t->same('button', $widget['widget']['field_type_label']);
        $t->same('Yes', $widget['widget']['current_value']);
        $t->same(1, $widget['widget']['action_count']);
        $t->same(false, $widget['widget']['executes_action']);

        $structure = $widget['structure_parent'];
        $t->same('annotation_struct_parent_parent_tree', $structure['source']);
        $t->same('widget_parent_field_struct_parent', $structure['struct_parent_source']);
        $t->same(25, $structure['key']);
        $t->same(40, $structure['struct_object']);
        $t->same(20, $structure['field_object']);
        $t->same([20], $structure['field_chain']);
        $t->same('FormControl', $structure['raw_role']);
        $t->same('Form', $structure['role']);
        $t->same(true, $structure['role_mapped']);
        $t->same('Publish approval widget', $structure['title']);
        $t->same('Approval button review', $structure['alternate_text']);
        $t->same('Approval actual text review', $structure['actual_text']);
        $t->same([7], $structure['annotation_objects']);
        $t->same(true, $structure['current_annotation_object_ref_matched']);
        $t->same(true, $structure['current_page_annotation']);
        $t->same(true, $structure['review_only']);
        $t->same(false, $structure['visible_text_source']);

        $stale = $page['annotations'][1];
        $t->same('Widget', $stale['subtype']);
        $t->same(8, $stale['annotation_object']);
        $t->same('stale.choice', $stale['widget']['field_name']);
        $t->same(false, array_key_exists('struct_parent', $stale));
        $t->same(false, array_key_exists('structure_parent', $stale));

        $encoded = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        $t->contains('Publish approval widget', $encoded);
        $t->same(false, str_contains($encoded, 'Stale field-only structure'));
        $t->contains('Visible widget structure boundary text', $plainText);
        $t->same(false, str_contains($plainText, 'approval.publish'));
        $t->same(false, str_contains($plainText, 'Publish approval widget'));
        $t->same(false, str_contains($plainText, 'Approval actual text review'));
        $t->same(false, str_contains($plainText, 'Stale field text review'));
        $t->same(false, str_contains($plainText, 'https://example.com/widget-approval'));
    },
];
