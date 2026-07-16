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

$objectStreamArrayMemberPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object-stream array boundary body) Tj ET';

    $compressedMembers = [
        20 => '[6 0 R (40 0 R literal decoy) [41 0 R] << /Nested 42 0 R >> % 43 0 R comment decoy' . "\n" . ']',
        21 => '[10 0 R]',
        22 => '[12 0 R]',
        30 => '[44 0 R]',
    ];

    $memberData = '';
    $headerPairs = [];
    foreach ($compressedMembers as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($memberData);
        $memberData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs) . ' ';
    $payload = $header . $memberData;
    $compressedPayload = gzcompress($payload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException('Unable to compress AcroForm object-stream array fixture.');
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /TM (profile-parent-map) /V (parent@example.test) /DV (parent-draft@example.test) /MaxLen 80 /Kids 21 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Parent 6 0 R /T (email) /TU (Public editor email) /TM (profile.email.export) /V (editor@example.test) /DV (draft@example.test) /Kids 22 0 R >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /FT /Tx /T (literal.decoy) /V (Literal array decoy value must not surface) >>\nendobj\n"
        . "41 0 obj\n<< /FT /Tx /T (nested.array.decoy) /V (Nested array decoy value must not surface) >>\nendobj\n"
        . "42 0 obj\n<< /FT /Tx /T (nested.dictionary.decoy) /V (Nested dictionary decoy value must not surface) >>\nendobj\n"
        . "43 0 obj\n<< /FT /Tx /T (comment.decoy) /V (Comment array decoy value must not surface) >>\nendobj\n"
        . "44 0 obj\n<< /FT /Tx /T (unreferenced.compressed.array.decoy) /V (Unreferenced array decoy value must not surface) >>\nendobj\n"
        . "50 0 obj\n<< /Type /ObjStm /N " . count($compressedMembers) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'expands object-stream AcroForm Fields and Kids array members before field repair' => static function (
        TestRunner $t
    ) use ($objectStreamArrayMemberPdf, $fieldsByName): void {
        $pdf = $objectStreamArrayMemberPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $t->same(10, $field['object']);
        $t->same('email', $field['partial_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('editor@example.test', $field['value']);
        $t->same('draft@example.test', $field['default_value']);
        $t->same('Public editor email', $field['alternate_name']);
        $t->same('profile.email.export', $field['mapping_name']);
        $t->same([6, 10], array_column($field['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($field['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile parent label', 'Public editor email'], array_column($field['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['profile-parent-map', 'profile.email.export'], array_column($field['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DA', 'MaxLen'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(10, $field['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same(10, $field['value_state']['hierarchy_boundary']['default_value_source_object']);
        $t->same(6, $field['value_state']['hierarchy_boundary']['max_length_source_object']);
        $t->same(80, $field['max_length']);
        $t->same(true, $field['max_length_review']['max_length_inherited']);

        $t->same([12], array_column($field['widgets'], 'object'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        foreach ([
            'literal.decoy',
            'nested.array.decoy',
            'nested.dictionary.decoy',
            'comment.decoy',
            'unreferenced.compressed.array.decoy',
        ] as $decoyName) {
            $t->true(!isset($fields[$decoyName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyName));
        }

        foreach ([
            'Literal array decoy value must not surface',
            'Nested array decoy value must not surface',
            'Nested dictionary decoy value must not surface',
            'Comment array decoy value must not surface',
            'Unreferenced array decoy value must not surface',
            'editor@example.test',
            'parent@example.test',
            'Public editor email',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm object-stream array boundary body', $visibleText);
    },
];
