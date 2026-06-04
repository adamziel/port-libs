<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$pageWidgetFieldBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR << /Font << /Helv 40 0 R >> >> >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.email) /V (listed@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (omitted.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.note) /V (inline page widget value) /Rect [72 560 320 584] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.secret) /V (detached widget value must not surface) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs AcroForm field discovery from page owned widget annotations only' => static function (TestRunner $t) use ($pageWidgetFieldBoundaryPdf, $fieldsByName): void {
        $pdf = $pageWidgetFieldBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['listed.email', 'omitted.category', 'inline.note'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.email'];
        $t->same(6, $listed['object']);
        $t->same('listed@example.test', $listed['value']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));

        $omitted = $fields['omitted.category'];
        $t->same(10, $omitted['object']);
        $t->same('choice', $omitted['field_type_label']);
        $t->same('page', $omitted['value']);
        $t->same([['export' => 'post', 'label' => 'post'], ['export' => 'page', 'label' => 'page']], $omitted['options']);
        $t->same([12], array_column($omitted['widgets'], 'object'));
        $t->same([1], array_column($omitted['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($omitted['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $omitted['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $omitted['field_hierarchy']['executes_form_actions']);
        $t->same(false, $omitted['field_hierarchy']['executes_javascript']);

        $inline = $fields['inline.note'];
        $t->same(14, $inline['object']);
        $t->same('text', $inline['field_type_label']);
        $t->same('inline page widget value', $inline['value']);
        $t->same([14], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $inline['value_state']['hierarchy_boundary']['current_value_source']);

        $t->true(!isset($fields['detached.secret']));
        $t->true(str_contains($visibleText, 'Visible AcroForm page widget boundary body'));
        $t->true(!str_contains($visibleText, 'detached widget value must not surface'));
        $t->true(!str_contains($visibleText, 'inline page widget value'));
    },
];
