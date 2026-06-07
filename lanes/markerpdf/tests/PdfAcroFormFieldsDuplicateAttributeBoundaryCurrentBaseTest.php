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

$duplicateFieldAttributePdf = static function (): string {
    $pageText = 'BT /Current 12 Tf 72 720 Td (Visible AcroForm duplicate attribute body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /Current 40 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true "
        . "/DA (/Stale 9 Tf 1 0 0 rg) /DA (/Current 11 Tf 0 0 1 rg) "
        . "/DR << /Font << /Stale 41 0 R >> >> /DR << /Font << /Current 40 0 R >> >> "
        . "/Q 0 /Q 2 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Btn /FT /Tx /T (stale.profile) /T (profile) "
        . "/TU (Stale parent label) /TU (Profile label) "
        . "/TM (stale-parent-export) /TM (profile-export) "
        . "/V (stale parent value) /V (Parent current review value) "
        . "/DV (stale parent default) /DV (Parent default review value) "
        . "/MaxLen 5 /MaxLen 80 /Q 1 /Q 2 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Parent 6 0 R /T (stale.email) /T (email) "
        . "/TU (Stale email label) /TU (Editor email label) "
        . "/TM (stale-email-export) /TM (profile.email.export) "
        . "/V (stale child value) /V (editor@example.test) "
        . "/DV (stale child default) /DV (draft@example.test) "
        . "/MaxLen 3 /MaxLen 64 /Q 0 /Q 1 /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "%%EOF";
};

$duplicateAcroFormDefaultPdf = static function (): string {
    $pageText = 'BT /Current 12 Tf 72 720 Td (Visible AcroForm duplicate default body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /Current 40 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true "
        . "/DA (/Stale 7 Tf 1 0 0 rg) /DA (/Current 10 Tf 0 0 1 rg) "
        . "/DR << /Font << /Stale 41 0 R >> >> /DR << /Font << /Current 40 0 R >> >> "
        . "/Q 0 /Q 2 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (defaults.email) /V (defaults@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last duplicate AcroForm field attributes before WordPress form review' => static function (
        TestRunner $t
    ) use ($duplicateFieldAttributePdf, $fieldsByName): void {
        $pdf = $duplicateFieldAttributePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $t->same(8, $field['object']);
        $t->same('profile.email', $field['name']);
        $t->same('email', $field['partial_name']);
        $t->same('Editor email label', $field['alternate_name']);
        $t->same('profile.email.export', $field['mapping_name']);
        $t->same('Tx', $field['field_type']);
        $t->same('text', $field['field_type_label']);
        $t->same('editor@example.test', $field['value']);
        $t->same('draft@example.test', $field['default_value']);
        $t->same(64, $field['max_length']);
        $t->same(1, $field['quadding']);
        $t->same('center', $field['text_alignment']);
        $t->same([6, 8], array_column($field['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($field['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DA', 'DR'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV', 'Q', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(8, $field['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same(8, $field['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same(8, $field['max_length_review']['max_length_source_object']);
        $t->same(false, $field['max_length_review']['max_length_inherited']);
        $t->same('field_terminal', $field['quadding_review']['quadding_source_boundary']);
        $t->same([10], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same('Current', $field['default_appearance']['font_resource']);
        $t->same(11.0, $field['default_appearance']['font_size']);
        $t->same(true, $field['default_appearance']['font_resource_resolved']);
        $t->same(40, $field['default_appearance']['font_resource_object']);
        $t->same('Helvetica', $field['default_appearance']['font_resource_base_font']);

        foreach ([
            'stale.profile',
            'stale.email',
            'Stale parent label',
            'Stale email label',
            'stale parent value',
            'stale child value',
            'stale child default',
            'stale-email-export',
            'Courier',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm duplicate attribute body', $visibleText);
        $t->same(false, str_contains($visibleText, 'editor@example.test'));
        $t->same(false, str_contains($visibleText, 'draft@example.test'));
    },
    'uses the last duplicate AcroForm default appearance and resource attributes' => static function (
        TestRunner $t
    ) use ($duplicateAcroFormDefaultPdf, $fieldsByName): void {
        $pdf = $duplicateAcroFormDefaultPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['defaults.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(['source' => 'acroform', 'object' => null], [
            'source' => $form['default_resources']['source'],
            'object' => $form['default_resources']['object'],
        ]);
        $t->same(['Current'], array_keys($form['default_resources']['fonts']));
        $t->same(40, $form['default_resources']['fonts']['Current']['object']);
        $t->same('Helvetica', $form['default_resources']['fonts']['Current']['base_font']);

        $field = $fields['defaults.email'];
        $t->same('defaults@example.test', $field['value']);
        $t->same('Current', $field['default_appearance']['font_resource']);
        $t->same(10.0, $field['default_appearance']['font_size']);
        $t->same(true, $field['default_appearance']['font_resource_resolved']);
        $t->same(40, $field['default_appearance']['font_resource_object']);
        $t->same('Helvetica', $field['default_appearance']['font_resource_base_font']);
        $t->same(2, $field['quadding']);
        $t->same('right', $field['text_alignment']);
        $t->same('acroform_default', $field['quadding_review']['quadding_source_boundary']);

        $t->same(false, str_contains($encoded, 'Stale'));
        $t->same(false, str_contains($encoded, 'Courier'));
        $t->same('Visible AcroForm duplicate default body', $visibleText);
        $t->same(false, str_contains($visibleText, 'defaults@example.test'));
    },
];
