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

$selfReferentialKidsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm self Kids cycle body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.self) /TU (Self cycle title label) /V (Self cycle field value) /DV (Self cycle default value) /MaxLen 80 /Kids [6 0 R 8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (detached.self.decoy) /V (Detached self cycle decoy value) /Kids [10 0 R] >>\nendobj\n"
        . "%%EOF";
};

$ancestorCycleKidsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm ancestor Kids cycle body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile root label) /TM (profile-root-map) /V (parent@example.test) /DV (default@example.test) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [10 0 R 14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.ancestor.decoy) /V (Detached ancestor cycle decoy value) /Kids [20 0 R] >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves terminal AcroForm field when Kids contains a self cycle' => static function (
        TestRunner $t
    ) use ($selfReferentialKidsPdf, $fieldsByName): void {
        $pdf = $selfReferentialKidsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.self'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.self'];
        $t->same(6, $field['object']);
        $t->same('article.self', $field['name']);
        $t->same('Self cycle title label', $field['alternate_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Self cycle field value', $field['value']);
        $t->same('Self cycle default value', $field['default_value']);
        $t->same(80, $field['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(6, $field['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'detached.self.decoy'));
        $t->same('Visible AcroForm self Kids cycle body', $visibleText);
        $t->true(!str_contains($visibleText, 'Self cycle field value'));
        $t->true(!str_contains($visibleText, 'Self cycle default value'));
        $t->true(!str_contains($visibleText, 'Detached self cycle decoy value'));
    },
    'bounds ancestor AcroForm Kids cycles to the referenced terminal branch' => static function (
        TestRunner $t
    ) use ($ancestorCycleKidsPdf, $fieldsByName): void {
        $pdf = $ancestorCycleKidsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $t->same(12, $field['object']);
        $t->same('profile.email', $field['name']);
        $t->same('email', $field['partial_name']);
        $t->same('Email child label', $field['alternate_name']);
        $t->same('profile.email.export', $field['mapping_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('editor@example.test', $field['value']);
        $t->same('default@example.test', $field['default_value']);
        $t->same([10, 12], array_column($field['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($field['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile root label', 'Email child label'], array_column($field['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['profile-root-map', 'profile.email.export'], array_column($field['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $field['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(64, $field['max_length']);
        $t->same(true, $field['max_length_review']['max_length_inherited']);
        $t->same([14], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'detached.ancestor.decoy'));
        $t->same('Visible AcroForm ancestor Kids cycle body', $visibleText);
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'parent@example.test'));
        $t->true(!str_contains($visibleText, 'Detached ancestor cycle decoy value'));
    },
];
