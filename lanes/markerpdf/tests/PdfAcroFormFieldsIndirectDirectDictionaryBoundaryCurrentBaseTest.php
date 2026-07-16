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

$indirectDirectDictionaryFieldsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect direct dictionary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n[\n"
        . "<< /FT /Tx /T (indirect.direct.root) /TU (Indirect direct root label) /TM (indirect-direct-root-export) /V (Indirect direct root value) /DV (Indirect direct root default) /MaxLen 80 /Kids [8 0 R] >>\n"
        . "<< /FT /Tx /T (indirect.direct.parent) /TU (Indirect direct parent label) /TM (indirect-direct-parent-export) /V (Parent fallback value) /DV (Parent fallback default) /MaxLen 40 /Kids 21 0 R >>\n"
        . "(90 0 R) [91 0 R] << /Nested << /FT /Tx /T (fields.nested.dict.decoy) /V (Fields nested dictionary decoy) >> >> % << /FT /Tx /T (fields.comment.decoy) /V (Fields comment decoy) >>\n"
        . "]\nendobj\n"
        . "21 0 obj\n[\n"
        . "<< /T (child) /TU (Indirect direct child label) /TM (indirect-direct-child-export) /V (Indirect direct child value) /Kids [12 0 R] >>\n"
        . "(92 0 R) [93 0 R] << /Nested << /T (kids.nested.dict.decoy) /V (Kids nested dictionary decoy) >> >> % << /T (kids.comment.decoy) /V (Kids comment decoy) >>\n"
        . "]\nendobj\n"
        . "90 0 obj\n<< /FT /Tx /T (fields.literal.ref.decoy) /V (Fields literal ref decoy) >>\nendobj\n"
        . "91 0 obj\n<< /FT /Tx /T (fields.nested.array.decoy) /V (Fields nested array decoy) >>\nendobj\n"
        . "92 0 obj\n<< /FT /Tx /T (kids.literal.ref.decoy) /V (Kids literal ref decoy) >>\nendobj\n"
        . "93 0 obj\n<< /FT /Tx /T (kids.nested.array.decoy) /V (Kids nested array decoy) >>\nendobj\n"
        . "%%EOF";
};

return [
    'materializes direct AcroForm dictionaries inside indirect Fields and Kids arrays' => static function (
        TestRunner $t
    ) use ($indirectDirectDictionaryFieldsPdf, $fieldsByName): void {
        $pdf = $indirectDirectDictionaryFieldsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['indirect.direct.root', 'indirect.direct.parent.child'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $root = $fields['indirect.direct.root'];
        $t->true(is_int($root['object']) && $root['object'] > 93);
        $t->same('text', $root['field_type_label']);
        $t->same('Indirect direct root value', $root['value']);
        $t->same('Indirect direct root default', $root['default_value']);
        $t->same('Indirect direct root label', $root['alternate_name']);
        $t->same('indirect-direct-root-export', $root['mapping_name']);
        $t->same(80, $root['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $root['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $root['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $root['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($root['widgets'], 'object'));
        $t->same([0], array_column($root['widgets'], 'page_index'));
        $t->same([3], array_column($root['widgets'], 'page_object'));
        $t->same([0], array_column($root['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($root['widgets'], 'referenced_from_page_annots'));

        $child = $fields['indirect.direct.parent.child'];
        $parentObject = $child['field_hierarchy']['ancestor_objects'][0] ?? null;
        $t->true(is_int($parentObject) && $parentObject > 93);
        $t->true(is_int($child['object']) && $child['object'] > 93 && $child['object'] !== $parentObject);
        $t->same('child', $child['partial_name']);
        $t->same('text', $child['field_type_label']);
        $t->same('Indirect direct child value', $child['value']);
        $t->same('Parent fallback default', $child['default_value']);
        $t->same('Indirect direct child label', $child['alternate_name']);
        $t->same('indirect-direct-child-export', $child['mapping_name']);
        $t->same(40, $child['max_length']);
        $t->same([$parentObject, $child['object']], array_column($child['field_hierarchy']['path'], 'object'));
        $t->same(['indirect.direct.parent', 'child'], array_column($child['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $child['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $child['field_hierarchy']['local_attributes']);
        $t->same('field_terminal_override', $child['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same($child['object'], $child['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same($parentObject, $child['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same([12], array_column($child['widgets'], 'object'));
        $t->same([1], array_column($child['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($child['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'fields.literal.ref.decoy',
            'fields.nested.array.decoy',
            'fields.nested.dict.decoy',
            'fields.comment.decoy',
            'kids.literal.ref.decoy',
            'kids.nested.array.decoy',
            'kids.nested.dict.decoy',
            'kids.comment.decoy',
        ] as $decoyName) {
            $t->true(!isset($fields[$decoyName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyName));
        }

        foreach ([
            'Indirect direct root value',
            'Indirect direct child value',
            'Parent fallback value',
            'Fields nested dictionary decoy',
            'Kids nested dictionary decoy',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }
        $t->true(str_contains($visibleText, 'Visible AcroForm indirect direct dictionary body'));
    },
];
