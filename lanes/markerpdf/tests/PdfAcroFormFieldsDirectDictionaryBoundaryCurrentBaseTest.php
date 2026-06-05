<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$directAcroFormFieldDictionaryBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm dictionary boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [\n"
        . "<< /FT /Tx /T (direct.root) /TU (Direct root label) /TM (direct-root-export) /V (Direct root value) /DV (Direct root default) /MaxLen 80 /Kids [8 0 R] >>\n"
        . "<< /FT /Tx /T (direct.parent) /TU (Direct parent label) /TM (direct-parent-map) /V (Parent direct value) /DV (Parent direct default) /MaxLen 32 /Kids [\n"
        . "<< /T (child) /TU (Direct child label) /TM (direct-child-export) /V (Direct child terminal value) /Kids [12 0 R] >>\n"
        . "(99 0 R) [101 0 R] << /Nested << /T (kids.nested.dict.decoy) /V (Kids nested dictionary decoy) >> >> % << /FT /Tx /T (kids.comment.decoy) /V (Kids comment decoy) >>\n"
        . "] >>\n"
        . "(102 0 R) [104 0 R] << /Nested << /FT /Tx /T (fields.nested.dict.decoy) /V (Fields nested dictionary decoy) >> >> % << /FT /Tx /T (fields.comment.decoy) /V (Fields comment decoy) >>\n"
        . "] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /FT /Tx /T (decoy.literal) /V (Literal decoy value) >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (kids.literal.ref.decoy) /V (Kids literal ref decoy) >>\nendobj\n"
        . "101 0 obj\n<< /FT /Tx /T (kids.nested.array.decoy) /V (Kids nested array decoy) >>\nendobj\n"
        . "102 0 obj\n<< /FT /Tx /T (fields.literal.ref.decoy) /V (Fields literal ref decoy) >>\nendobj\n"
        . "104 0 obj\n<< /FT /Tx /T (fields.nested.array.decoy) /V (Fields nested array decoy) >>\nendobj\n"
        . "110 0 obj\n<< /FT /Tx /T (detached.highwater.decoy) /V (Detached highwater decoy) >>\nendobj\n"
        . "%%EOF";
};

return [
    'materializes direct AcroForm Fields and Kids dictionaries without promoting nested decoys' => static function (
        TestRunner $t
    ) use ($directAcroFormFieldDictionaryBoundaryPdf, $fieldsByName): void {
        $pdf = $directAcroFormFieldDictionaryBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.root', 'direct.parent.child'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $root = $fields['direct.root'];
        $t->true(is_int($root['object']) && $root['object'] > 110);
        $t->same('text', $root['field_type_label']);
        $t->same('Direct root value', $root['value']);
        $t->same('Direct root default', $root['default_value']);
        $t->same(80, $root['max_length']);
        $t->same('Direct root label', $root['alternate_name']);
        $t->same('direct-root-export', $root['mapping_name']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $root['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $root['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $root['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($root['widgets'], 'object'));
        $t->same([0], array_column($root['widgets'], 'page_index'));
        $t->same([3], array_column($root['widgets'], 'page_object'));
        $t->same([0], array_column($root['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($root['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $root['widgets'][0]['rect']);

        $child = $fields['direct.parent.child'];
        $parentObject = $child['field_hierarchy']['ancestor_objects'][0] ?? null;
        $t->true(is_int($parentObject) && $parentObject > 110);
        $t->true(is_int($child['object']) && $child['object'] > 110 && $child['object'] !== $parentObject);
        $t->same('child', $child['partial_name']);
        $t->same('text', $child['field_type_label']);
        $t->same('Direct child terminal value', $child['value']);
        $t->same('Parent direct default', $child['default_value']);
        $t->same(32, $child['max_length']);
        $t->same('Direct child label', $child['alternate_name']);
        $t->same('direct-child-export', $child['mapping_name']);
        $t->same([$parentObject, $child['object']], array_column($child['field_hierarchy']['path'], 'object'));
        $t->same(['direct.parent', 'child'], array_column($child['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $child['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $child['field_hierarchy']['local_attributes']);
        $t->same('field_terminal_override', $child['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same($child['object'], $child['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same($parentObject, $child['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same($parentObject, $child['value_state']['hierarchy_boundary']['max_length_source_object']);
        $t->same([12], array_column($child['widgets'], 'object'));
        $t->same([0], array_column($child['widgets'], 'page_index'));
        $t->same([3], array_column($child['widgets'], 'page_object'));
        $t->same([1], array_column($child['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($child['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'decoy.literal',
            'kids.literal.ref.decoy',
            'kids.nested.array.decoy',
            'kids.nested.dict.decoy',
            'kids.comment.decoy',
            'fields.literal.ref.decoy',
            'fields.nested.array.decoy',
            'fields.nested.dict.decoy',
            'fields.comment.decoy',
            'detached.highwater.decoy',
        ] as $decoyName) {
            $t->true(!isset($fields[$decoyName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyName));
        }

        foreach ([
            'Direct root value',
            'Direct child terminal value',
            'Parent direct default',
            'Kids nested dictionary decoy',
            'Fields nested dictionary decoy',
            'Detached highwater decoy',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->true(str_contains($visibleText, 'Visible direct AcroForm dictionary boundary body'));
    },
];
