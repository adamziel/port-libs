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

$directParentNoKidsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent no Kids boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R 18 0 R] /NeedAppearances true /DA (/Fallback 9 Tf 0 0 0 rg) /DR << /Font << /Fallback 40 0 R /ParentFont 41 0 R >> >> >>\nendobj\n"
        . "12 0 obj\n<< /Parent << /FT /Tx /T (profile) /TU (Direct parent no Kids label) /TM (profile-direct-nokids-map) /Ff 4097 /V (Parent stale current value) /DV (Parent draft value) /MaxLen 80 /Q 2 /DA (/ParentFont 11 Tf 0 0 1 rg) >> /T (email) /TU (Editor email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /Parent << /FT /Tx /T (detached.parent.decoy) /TU (Detached parent label must not surface) /TM (detached-parent-map) /DV (Detached parent default must not surface) /MaxLen 5 /Kids [] >> /FT /Tx /T (local.only) /TU (Local child label) /TM (local-only-export) /V (Local child value) /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs direct AcroForm Parent field dictionaries that omit Kids for listed children' => static function (
        TestRunner $t
    ) use ($directParentNoKidsBoundaryPdf, $fieldsByName): void {
        $pdf = $directParentNoKidsBoundaryPdf();
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
        $t->true(is_int($parentObject) && $parentObject > 41);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Editor email child label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('Parent draft value', $email['default_value']);
        $t->same(80, $email['max_length']);
        $t->same(true, in_array('read_only', $email['flag_names'], true));
        $t->same(true, in_array('multiline', $email['flag_names'], true));
        $t->same(2, $email['quadding']);
        $t->same('right', $email['text_alignment']);
        $t->same([$parentObject, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Direct parent no Kids label', 'Editor email child label'], array_column($email['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['FT', 'Ff', 'DV', 'DA', 'DR', 'Q', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same($parentObject, $email['field_hierarchy']['field_type_source_object']);
        $t->same($parentObject, $email['field_hierarchy']['flags_source_object']);
        $t->same($parentObject, $email['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same($parentObject, $email['max_length_review']['max_length_source_object']);
        $t->same(true, $email['max_length_review']['max_length_inherited']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(false, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_default']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['max_length_inherited']);
        $t->same('ParentFont', $email['default_appearance']['font_resource']);
        $t->same(11.0, $email['default_appearance']['font_size']);
        $t->same('field', $email['default_appearance']['source']);
        $t->same($parentObject, $email['default_appearance']['source_object']);
        $t->same(true, $email['default_appearance']['font_resource_resolved']);
        $t->same(41, $email['default_appearance']['font_resource_object']);
        $t->same('Times-Roman', $email['default_appearance']['font_resource_base_font']);
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
        $t->same(['DA', 'DR'], $local['field_hierarchy']['inherited_attributes']);
        $t->same('Fallback', $local['default_appearance']['font_resource']);
        $t->same([20], array_column($local['widgets'], 'object'));
        $t->same([1], array_column($local['widgets'], 'page_annotation_index'));

        foreach ([
            'detached.parent.decoy',
            'Detached parent label must not surface',
            'detached-parent-map',
            'Detached parent default must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        foreach ([
            'editor@example.test',
            'Parent stale current value',
            'Parent draft value',
            'Direct parent no Kids label',
            'Editor email child label',
            'Local child value',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm direct Parent no Kids boundary body', $visibleText);
    },
];
