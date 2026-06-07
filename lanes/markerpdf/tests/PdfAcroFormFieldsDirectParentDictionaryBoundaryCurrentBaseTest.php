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

$directParentDictionaryBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent dictionary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.inline) /TU (Direct Parent inline label) /TM (direct-parent-inline-export) /V (Direct Parent inline value) /DV (Direct Parent inline default) /MaxLen 44 >> /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent << /Type /Page /T (direct.parent.page.decoy) /TU (Direct Parent page decoy label) /V (Direct Parent page decoy value) >> /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.emptykids.decoy) /TU (Direct Parent empty Kids label) /V (Direct Parent empty Kids value) /Kids [] >> /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs page-owned widgets whose Parent is a direct AcroForm field dictionary' => static function (
        TestRunner $t
    ) use ($directParentDictionaryBoundaryPdf, $fieldsByName): void {
        $pdf = $directParentDictionaryBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.parent.inline'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['direct.parent.inline'];
        $t->true(is_int($field['object'] ?? null) && ($field['object'] ?? 0) > 16);
        $t->same('direct.parent.inline', $field['name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Direct Parent inline value', $field['value']);
        $t->same('Direct Parent inline default', $field['default_value']);
        $t->same('Direct Parent inline label', $field['alternate_name']);
        $t->same('direct-parent-inline-export', $field['mapping_name']);
        $t->same(44, $field['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same($field['object'], $field['value_state']['hierarchy_boundary']['current_value_source_object']);

        $widgets = $field['widgets'];
        $t->same([8], array_column($widgets, 'object'));
        $t->same([0], array_column($widgets, 'page_index'));
        $t->same([3], array_column($widgets, 'page_object'));
        $t->same([0], array_column($widgets, 'page_annotation_index'));
        $t->same([true], array_column($widgets, 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $widgets[0]['rect']);

        foreach ([
            'direct.parent.page.decoy',
            'Direct Parent page decoy label',
            'Direct Parent page decoy value',
            'direct.parent.emptykids.decoy',
            'Direct Parent empty Kids label',
            'Direct Parent empty Kids value',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        foreach ([
            'Direct Parent inline value',
            'Direct Parent inline default',
            'Direct Parent inline label',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm direct Parent dictionary body', $visibleText);
    },
];
