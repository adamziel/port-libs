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

$acroFormParentOwnershipBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm parent ownership boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 34 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R 32 0 R] /NeedAppearances true >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (decoy.profile) /TU (Detached parent label must not surface) /TM (decoy.profile.map) /V (Detached parent value must not surface) /DV (Detached parent default must not surface) /MaxLen 6 /Kids [16 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Terminal email label) /TM (email.map) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /FT /Tx /T (valid.profile) /V (Inherited valid title) /DV (Draft valid title) /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Parent 30 0 R /T (title) /Kids [34 0 R] >>\nendobj\n"
        . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'bounds AcroForm Parent inheritance to parent Kids ownership before WordPress field review' => static function (
        TestRunner $t
    ) use ($acroFormParentOwnershipBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormParentOwnershipBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['email', 'valid.profile.title'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['email'];
        $t->same(12, $email['object']);
        $t->same('email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Terminal email label', $email['alternate_name']);
        $t->same('email.map', $email['mapping_name']);
        $t->same(null, $email['field_type']);
        $t->same('unknown', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same(null, $email['default_value']);
        $t->same([12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same([], $email['field_hierarchy']['ancestor_objects']);
        $t->same(['V'], $email['field_hierarchy']['local_attributes']);
        $t->same([], $email['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(12, $email['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same(null, $email['max_length']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));
        $t->same('Terminal email label', $email['field_name_review']['wordpress_label']);
        $t->same(['email'], $email['field_name_review']['partial_name_path']);

        $valid = $fields['valid.profile.title'];
        $t->same(32, $valid['object']);
        $t->same('valid.profile.title', $valid['name']);
        $t->same('text', $valid['field_type_label']);
        $t->same('Inherited valid title', $valid['value']);
        $t->same('Draft valid title', $valid['default_value']);
        $t->same([30, 32], array_column($valid['field_hierarchy']['path'], 'object'));
        $t->same(['valid.profile', 'title'], array_column($valid['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'V', 'DV'], $valid['field_hierarchy']['inherited_attributes']);
        $t->same('field_hierarchy_inherited', $valid['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(30, $valid['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same([34], array_column($valid['widgets'], 'object'));
        $t->same([1], array_column($valid['widgets'], 'page_annotation_index'));

        foreach ([
            'decoy.profile',
            'Detached parent label must not surface',
            'decoy.profile.map',
            'Detached parent value must not surface',
            'Detached parent default must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm parent ownership boundary body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'Inherited valid title'));
        $t->true(!str_contains($visibleText, 'Terminal email label'));
        $t->same(false, $email['field_name_review']['alternate_name_used_as_visible_text']);
        $t->same(false, $email['field_name_review']['executes_form_actions']);
        $t->same(false, $email['field_hierarchy']['executes_javascript']);
    },
];
