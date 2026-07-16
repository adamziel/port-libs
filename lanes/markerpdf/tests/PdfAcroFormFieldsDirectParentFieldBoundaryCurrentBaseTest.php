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

$directParentFieldBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent field boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R 18 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "12 0 obj\n<< /Parent << /FT /Tx /T (profile) /TU (Direct parent label) /TM (profile-parent-map) /V (Inherited direct parent value) /DV (Inherited direct parent default) /MaxLen 80 /DA (/Helv 10 Tf 0 0 1 rg) /Kids [12 0 R] >> /T (email) /TU (Email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /Parent << /FT /Tx /T (detached.parent.decoy) /TU (Detached parent label must not surface) /TM (detached-parent-map) /V (Detached direct parent value must not surface) /DV (Detached direct parent default must not surface) /MaxLen 5 /Kids [99 0 R] >> /FT /Tx /T (local.only) /TU (Local child label) /TM (local-only-export) /V (Local child value) /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'materializes direct AcroForm Parent field dictionaries only when Kids owns the listed child' => static function (
        TestRunner $t
    ) use ($directParentFieldBoundaryPdf, $fieldsByName): void {
        $pdf = $directParentFieldBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email', 'local.only'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $parentObject = $email['field_hierarchy']['ancestor_objects'][0] ?? null;
        $t->same(12, $email['object']);
        $t->true(is_int($parentObject) && $parentObject > 99);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Email child label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('Inherited direct parent default', $email['default_value']);
        $t->same(80, $email['max_length']);
        $t->same([$parentObject, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same($parentObject, $email['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same($parentObject, $email['max_length_review']['max_length_source_object']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([3], array_column($email['widgets'], 'page_object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $local = $fields['local.only'];
        $t->same(18, $local['object']);
        $t->same('local.only', $local['name']);
        $t->same('text', $local['field_type_label']);
        $t->same('Local child value', $local['value']);
        $t->same(null, $local['default_value']);
        $t->same(null, $local['max_length']);
        $t->same([], $local['field_hierarchy']['ancestor_objects']);
        $t->same(['FT', 'V'], $local['field_hierarchy']['local_attributes']);
        $t->same(['V'], $local['field_hierarchy']['local_value_attributes']);
        $t->same(['DA'], $local['field_hierarchy']['inherited_attributes']);
        $t->same([20], array_column($local['widgets'], 'object'));
        $t->same([1], array_column($local['widgets'], 'page_annotation_index'));

        foreach ([
            'detached.parent.decoy',
            'Detached parent label must not surface',
            'detached-parent-map',
            'Detached direct parent value must not surface',
            'Detached direct parent default must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        foreach ([
            'editor@example.test',
            'Inherited direct parent default',
            'Local child value',
            'Email child label',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm direct Parent field boundary body', $visibleText);
    },
];
