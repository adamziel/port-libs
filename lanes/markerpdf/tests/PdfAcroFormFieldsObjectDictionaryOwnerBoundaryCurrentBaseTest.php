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

$acroFormFieldObjectDictionaryOwnerPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm field object dictionary-owner body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 20 0 R 30 0 R 40 0 R 50 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (owner.valid) /TU (Owner valid label) /TM (owner-valid-export) /V (Owner valid value) /DV (Owner valid default) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n(<< /FT /Tx /T (literal.owner.decoy) /TU (Literal owner label) /TM (literal-owner-export) /V (Literal owner value) >>)\nendobj\n"
        . "30 0 obj\n% << /FT /Tx /T (comment.owner.decoy) /TU (Comment owner label) /TM (comment-owner-export) /V (Comment owner value) >>\nnull\nendobj\n"
        . "40 0 obj\n[<< /FT /Tx /T (array.owner.decoy) /TU (Array owner label) /TM (array-owner-export) /V (Array owner value) >>]\nendobj\n"
        . "50 0 obj\n/NotAField << /FT /Tx /T (tail.owner.decoy) /TU (Tail owner label) /TM (tail-owner-export) /V (Tail owner value) >>\nendobj\n"
        . "%%EOF";
};

return [
    'requires top-level dictionaries for AcroForm field reference objects before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormFieldObjectDictionaryOwnerPdf, $fieldsByName): void {
        $pdf = $acroFormFieldObjectDictionaryOwnerPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['owner.valid'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['owner.valid'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Owner valid label', $field['alternate_name']);
        $t->same('owner-valid-export', $field['mapping_name']);
        $t->same('Owner valid value', $field['value']);
        $t->same('Owner valid default', $field['default_value']);
        $t->same(64, $field['max_length']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        foreach ([
            'literal.owner.decoy',
            'Literal owner label',
            'Literal owner value',
            'comment.owner.decoy',
            'Comment owner label',
            'Comment owner value',
            'array.owner.decoy',
            'Array owner label',
            'Array owner value',
            'tail.owner.decoy',
            'Tail owner label',
            'Tail owner value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm field object dictionary-owner body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Owner valid value'));
        $t->same(false, str_contains($visibleText, 'Owner valid label'));
    },
];
