<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$duplicateFieldsKeyPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate AcroForm Fields body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [99 0 R] /DA (/Helv 9 Tf 0 0 0 rg with literal /Fields [101 0 R]) /Fie#6Cds [6 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.current_duplicate) /TU (Current duplicate label) /TM (article.current_duplicate.export) /V (Current duplicate field value) /DV (Current duplicate draft) /MaxLen 96 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (stale.duplicate_fields) /TU (Stale duplicate label) /TM (stale.duplicate.export) /V (Stale duplicate field value must not surface) >>\nendobj\n"
        . "101 0 obj\n<< /FT /Tx /T (literal.fields.decoy) /V (Literal Fields decoy value must not surface) >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last top-level AcroForm Fields key before WordPress field review' => static function (
        TestRunner $t
    ) use ($duplicateFieldsKeyPdf, $fieldsByName): void {
        $pdf = $duplicateFieldsKeyPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.current_duplicate'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.current_duplicate'];
        $t->same(6, $field['object']);
        $t->same('article.current_duplicate', $field['name']);
        $t->same('Current duplicate label', $field['alternate_name']);
        $t->same('article.current_duplicate.export', $field['mapping_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Current duplicate field value', $field['value']);
        $t->same('Current duplicate draft', $field['default_value']);
        $t->same(96, $field['max_length']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.duplicate_fields'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale duplicate field value must not surface'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'literal.fields.decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Literal Fields decoy value must not surface'));
        $t->same('Visible duplicate AcroForm Fields body', $visibleText);
        $t->true(!str_contains($visibleText, 'Current duplicate field value'));
        $t->true(!str_contains($visibleText, 'Current duplicate draft'));
        $t->true(!str_contains($visibleText, 'Stale duplicate field value must not surface'));
        $t->true(!str_contains($visibleText, 'Literal Fields decoy value must not surface'));
    },
];
