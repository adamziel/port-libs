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

$directParentKidsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent Kids boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.kids) /TU (Direct Parent Kids label) /TM (direct-parent-kids-export) /V (Direct Parent Kids value) /DV (Direct Parent Kids default) /MaxLen 52 /Kids [<< /F 4 /P 3 0 R /Rect [72 640 320 664] /Sub#74ype /Widget >>] >> /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.wrongkid.decoy) /TU (Wrong direct Parent Kids label) /TM (wrong-direct-parent-kids-export) /V (Wrong direct Parent Kids value) /Kids [<< /Subtype /Widget /Parent 99 0 R /F 4 /P 3 0 R /Rect [72 600 320 624] >>] >> /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (detached.wrongkid.parent) /V (Detached wrong direct kid parent value) >>\nendobj\n"
        . "%%EOF";
};

return [
    'matches direct Parent field Kids widget dictionaries to the page-owned widget' => static function (
        TestRunner $t
    ) use ($directParentKidsBoundaryPdf, $fieldsByName): void {
        $pdf = $directParentKidsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.parent.kids'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['direct.parent.kids'];
        $t->true(is_int($field['object'] ?? null) && ($field['object'] ?? 0) > 99);
        $t->same('direct.parent.kids', $field['name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Direct Parent Kids label', $field['alternate_name']);
        $t->same('direct-parent-kids-export', $field['mapping_name']);
        $t->same('Direct Parent Kids value', $field['value']);
        $t->same('Direct Parent Kids default', $field['default_value']);
        $t->same(52, $field['max_length']);
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
            'direct.parent.wrongkid.decoy',
            'Wrong direct Parent Kids label',
            'wrong-direct-parent-kids-export',
            'Wrong direct Parent Kids value',
            'detached.wrongkid.parent',
            'Detached wrong direct kid parent value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        foreach ([
            'Direct Parent Kids value',
            'Direct Parent Kids default',
            'Direct Parent Kids label',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm direct Parent Kids boundary body', $visibleText);
        $t->same(false, $field['field_name_review']['field_value_used_as_visible_text']);
        $t->same(false, $field['field_hierarchy']['executes_form_actions']);
        $t->same(false, $field['field_hierarchy']['executes_javascript']);
    },
];
