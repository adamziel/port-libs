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

$detachedOwnerParentlessChildPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm detached owner boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Reachable profile label) /TM (reachable-profile-map) /V (Reachable parent value) /DV (Reachable draft value) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /T (email) /TU (Reachable email label) /TM (reachable.email.export) /V (reachable@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "80 0 obj\n<< /FT /Tx /T (detached.owner) /TU (Detached owner label) /TM (detached-owner-map) /V (Detached owner value must not surface) /DV (Detached owner draft must not surface) /MaxLen 4 /Kids [12 0 R] >>\nendobj\n"
        . "%%EOF";
};

return [
    'ignores detached parentless-child owners outside the reachable AcroForm Fields tree' => static function (
        TestRunner $t
    ) use ($detachedOwnerParentlessChildPdf, $fieldsByName): void {
        $pdf = $detachedOwnerParentlessChildPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $t->same(12, $field['object']);
        $t->same('email', $field['partial_name']);
        $t->same('Reachable email label', $field['alternate_name']);
        $t->same('reachable.email.export', $field['mapping_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('reachable@example.test', $field['value']);
        $t->same('Reachable draft value', $field['default_value']);
        $t->same(64, $field['max_length']);
        $t->same([6, 12], array_column($field['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($field['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $field['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same([14], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'detached.owner',
            'Detached owner label',
            'detached-owner-map',
            'Detached owner value must not surface',
            'Detached owner draft must not surface',
        ] as $detachedLeak) {
            $t->same(false, isset($fields[$detachedLeak]));
            $t->same(false, str_contains($encoded, $detachedLeak));
            $t->same(false, str_contains($visibleText, $detachedLeak));
        }

        $t->same('Visible AcroForm detached owner boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'reachable@example.test'));
        $t->same(false, str_contains($visibleText, 'Reachable email label'));
        $t->same(false, $field['field_name_review']['alternate_name_used_as_visible_text']);
        $t->same(false, $field['field_hierarchy']['executes_form_actions']);
        $t->same(false, $field['field_hierarchy']['executes_javascript']);
    },
];
