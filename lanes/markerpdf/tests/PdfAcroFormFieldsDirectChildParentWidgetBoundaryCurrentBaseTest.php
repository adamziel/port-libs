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

$directChildParentWidgetBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct child Parent widget body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /V (profile parent value) /DV (profile draft value) /MaxLen 64 /Kids [\n"
        . "<< /Parent 10 0 R /T (email) /TU (Direct child parent label) /TM (profile.email.export) /V (direct-child@example.test) /Kids [12 0 R] >>\n"
        . "<< /Parent 99 0 R /T (secret) /TU (Direct child decoy label) /TM (profile.secret.export) /V (direct child secret value must not surface) /Kids [16 0 R] >>\n"
        . "] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (detached.parent.decoy) /V (detached parent value must not surface) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'retains page widgets whose direct child field Parent points at the owning indirect parent' => static function (
        TestRunner $t
    ) use ($directChildParentWidgetBoundaryPdf, $fieldsByName): void {
        $pdf = $directChildParentWidgetBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $parentObject = $field['field_hierarchy']['ancestor_objects'][0] ?? null;
        $t->same(10, $parentObject);
        $t->true(is_int($field['object'] ?? null) && ($field['object'] ?? 0) > 99);
        $t->same('profile.email', $field['name']);
        $t->same('email', $field['partial_name']);
        $t->same('Direct child parent label', $field['alternate_name']);
        $t->same('profile.email.export', $field['mapping_name']);
        $t->same('Tx', $field['field_type']);
        $t->same('text', $field['field_type_label']);
        $t->same('direct-child@example.test', $field['value']);
        $t->same('profile draft value', $field['default_value']);
        $t->same(64, $field['max_length']);
        $t->same([10, $field['object']], array_column($field['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($field['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile parent label', 'Direct child parent label'], array_column($field['field_hierarchy']['path'], 'alternate_name'));
        $t->same([null, 'profile.email.export'], array_column($field['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same($field['object'], $field['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same(10, $field['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same(10, $field['max_length_review']['max_length_source_object']);
        $t->same(true, $field['max_length_review']['max_length_inherited']);

        $widgets = $field['widgets'];
        $t->same([12], array_column($widgets, 'object'));
        $t->same([0], array_column($widgets, 'page_index'));
        $t->same([3], array_column($widgets, 'page_object'));
        $t->same([0], array_column($widgets, 'page_annotation_index'));
        $t->same([true], array_column($widgets, 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $widgets[0]['rect']);
        $t->same(10, $widgets[0]['page_object'] === 3 ? $parentObject : null);
        $t->same(false, $field['field_name_review']['field_value_used_as_visible_text']);
        $t->same(false, $field['field_hierarchy']['executes_form_actions']);
        $t->same(false, $field['field_hierarchy']['executes_javascript']);

        foreach ([
            'profile.secret',
            'Direct child decoy label',
            'profile.secret.export',
            'direct child secret value must not surface',
            'detached.parent.decoy',
            'detached parent value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        foreach ([
            'direct-child@example.test',
            'profile parent value',
            'profile draft value',
            'Direct child parent label',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm direct child Parent widget body'));
    },
];
