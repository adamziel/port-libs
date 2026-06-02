<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Visible widget structure boundary text) Tj ET';
$pdf = "%PDF-2.0\n"
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

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($annotationPages) !== 1 || count($annotationPages[0]['annotations'] ?? []) !== 2) {
    throw new RuntimeException('Expected two current page widget annotations.');
}

$annotations = $annotationPages[0]['annotations'];
$widget = $annotations[0];
$stale = $annotations[1];
if (($widget['struct_parent'] ?? null) !== 25 || ($widget['structure_parent']['struct_object'] ?? null) !== 40) {
    throw new RuntimeException('Expected widget parent-field StructParent to resolve through the ParentTree.');
}
if (($widget['structure_parent']['current_annotation_object_ref_matched'] ?? null) !== true) {
    throw new RuntimeException('Expected ParentTree OBJR to match the current page widget annotation.');
}
if (array_key_exists('structure_parent', $stale)) {
    throw new RuntimeException('Expected stale field-only ParentTree OBJR to stay detached from the current widget.');
}
if ($lines !== ['Visible widget structure boundary text']
    || str_contains($plainText, 'approval.publish')
    || str_contains($plainText, 'Publish approval widget')
    || str_contains($plainText, 'Approval actual text review')
    || str_contains($plainText, 'Stale field text review')
    || str_contains($plainText, 'https://example.com/widget-approval')
) {
    throw new RuntimeException('Expected widget structure metadata and action targets to remain out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-annotation-parenttree-widget-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-annotation-parenttree-widget-review',
    'native_boundary' => 'visible page Widget annotations inherit terminal field StructParent only when ParentTree OBJR matches the widget object',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'annotation_objects' => array_column($annotations, 'annotation_object'),
    'resolved_widget_struct_parent' => $widget['struct_parent'] ?? null,
    'struct_parent_source' => $widget['struct_parent_source'] ?? null,
    'field_object' => $widget['struct_parent_field_object'] ?? null,
    'structure_role' => $widget['structure_parent']['role'] ?? null,
    'current_objr_matched' => $widget['structure_parent']['current_annotation_object_ref_matched'] ?? null,
    'stale_field_parent_detached' => !array_key_exists('structure_parent', $stale),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'approval.publish')
        && !str_contains($plainText, 'Publish approval widget')
        && !str_contains($plainText, 'Approval actual text review')
        && !str_contains($plainText, 'Stale field text review')
        && !str_contains($plainText, 'https://example.com/widget-approval'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-annotation-widget-structure-review ' . $htmlJson([
    'annotation_object' => $widget['annotation_object'] ?? null,
    'field_name' => $widget['widget']['field_name'] ?? null,
    'struct_parent' => $widget['struct_parent'] ?? null,
    'structure_parent' => [
        'source' => $widget['structure_parent']['source'] ?? null,
        'struct_parent_source' => $widget['structure_parent']['struct_parent_source'] ?? null,
        'struct_object' => $widget['structure_parent']['struct_object'] ?? null,
        'role' => $widget['structure_parent']['role'] ?? null,
        'title' => $widget['structure_parent']['title'] ?? null,
        'annotation_objects' => $widget['structure_parent']['annotation_objects'] ?? [],
        'current_annotation_object_ref_matched' => $widget['structure_parent']['current_annotation_object_ref_matched'] ?? null,
        'review_only' => $widget['structure_parent']['review_only'] ?? null,
    ],
]) . " -->\n";
