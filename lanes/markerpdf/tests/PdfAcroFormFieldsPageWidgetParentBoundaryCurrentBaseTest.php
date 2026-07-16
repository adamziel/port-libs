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

$acroFormPageWidgetParentBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget Parent boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [34 0 R 38 0 R 64 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [32 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "30 0 obj\n<< /FT /Tx /T (valid) /TU (Valid root label) /TM (valid.root.map) /V (Inherited valid value) /DV (Draft valid value) /MaxLen 80 /Kids [32 0 R 36 0 R] /DA (/Helv 10 Tf 0 0 1 rg) >>\nendobj\n"
        . "32 0 obj\n<< /Par#65nt 30 0 R /T (first) /TU (First label) /TM (valid.first.export) /V (first@example.test) /Kids [34 0 R] >>\nendobj\n"
        . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "36 0 obj\n<< /Par#65nt 30 0 R /T (second) /TU (Second label) /TM (valid.second.export) /V (second@example.test) /Kids [38 0 R] >>\nendobj\n"
        . "38 0 obj\n<< /Subtype /Widget /Parent 36 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "62 0 obj\n<< /Par#65nt 70 0 R /T (spoof.child) /TU (Spoof child label must not surface) /TM (spoof.child.map) /V (spoof@example.test) /Kids [64 0 R] >>\nendobj\n"
        . "64 0 obj\n<< /Subtype /Widget /Parent 62 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "70 0 obj\n<< /FT /Tx /T (detached.parent) /V (detached parent value must not surface) /Kids [72 0 R] >>\nendobj\n"
        . "72 0 obj\n<< /Subtype /Widget /Parent 70 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'bounds page widget AcroForm parent repair by escaped Parent ownership before WordPress field review' => static function (
        TestRunner $t
    ) use ($acroFormPageWidgetParentBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormPageWidgetParentBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['valid.first', 'valid.second'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $first = $fields['valid.first'];
        $t->same(32, $first['object']);
        $t->same('valid.first', $first['name']);
        $t->same('First label', $first['alternate_name']);
        $t->same('valid.first.export', $first['mapping_name']);
        $t->same('text', $first['field_type_label']);
        $t->same('first@example.test', $first['value']);
        $t->same('Draft valid value', $first['default_value']);
        $t->same(80, $first['max_length']);
        $t->same([30, 32], array_column($first['field_hierarchy']['path'], 'object'));
        $t->same(['valid', 'first'], array_column($first['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $first['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $first['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $first['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([34], array_column($first['widgets'], 'object'));
        $t->same([0], array_column($first['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($first['widgets'], 'referenced_from_page_annots'));

        $second = $fields['valid.second'];
        $t->same(36, $second['object']);
        $t->same('valid.second', $second['name']);
        $t->same('Second label', $second['alternate_name']);
        $t->same('valid.second.export', $second['mapping_name']);
        $t->same('text', $second['field_type_label']);
        $t->same('second@example.test', $second['value']);
        $t->same('Draft valid value', $second['default_value']);
        $t->same(80, $second['max_length']);
        $t->same([30, 36], array_column($second['field_hierarchy']['path'], 'object'));
        $t->same(['valid', 'second'], array_column($second['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $second['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $second['field_hierarchy']['local_value_attributes']);
        $t->same([38], array_column($second['widgets'], 'object'));
        $t->same([1], array_column($second['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($second['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'spoof.child',
            'Spoof child label must not surface',
            'spoof.child.map',
            'spoof@example.test',
            'detached.parent',
            'detached parent value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm page widget Parent boundary body'));
        $t->true(!str_contains($visibleText, 'first@example.test'));
        $t->true(!str_contains($visibleText, 'second@example.test'));
        $t->true(!str_contains($visibleText, 'Draft valid value'));
        $t->same(false, $first['field_name_review']['alternate_name_used_as_visible_text']);
        $t->same(false, $second['field_hierarchy']['executes_form_actions']);
        $t->same(false, $second['field_hierarchy']['executes_javascript']);
    },
];
