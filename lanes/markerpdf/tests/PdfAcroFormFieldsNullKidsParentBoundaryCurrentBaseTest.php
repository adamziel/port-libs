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

$acroFormNullKidsParentPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm null Kids parent body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 24 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (nullkids.email) /TU (Null Kids email label) /TM (nullkids.email.export) /V (nullkids@example.test) /DV (draft-nullkids@example.test) /MaxLen 64 /Kids null >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [320 664 72 640] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (emptykids.decoy) /TU (Explicit empty Kids decoy label) /TM (emptykids.decoy.export) /V (empty Kids decoy value) /Kids [] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (mismatchkids.decoy) /TU (Mismatched Kids decoy label) /TM (mismatchkids.decoy.export) /V (mismatched Kids decoy value) /Kids [18 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (malformedkids.decoy) /TU (Malformed Kids decoy label) /TM (malformedkids.decoy.export) /V (malformed Kids decoy value) /Kids 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /NotAnArray true >>\nendobj\n"
        . "24 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats explicit null Kids as absent during page-widget parent boundary repair' => static function (
        TestRunner $t
    ) use ($acroFormNullKidsParentPdf, $fieldsByName): void {
        $pdf = $acroFormNullKidsParentPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['nullkids.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['nullkids.email'];
        $t->same(6, $field['object']);
        $t->same('nullkids.email', $field['name']);
        $t->same('text', $field['field_type_label']);
        $t->same('nullkids@example.test', $field['value']);
        $t->same('draft-nullkids@example.test', $field['default_value']);
        $t->same('Null Kids email label', $field['alternate_name']);
        $t->same('nullkids.email.export', $field['mapping_name']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(64, $field['max_length']);
        $t->same(false, $field['max_length_review']['max_length_inherited']);

        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);
        $t->same(4, $field['widgets'][0]['annotation_flags']);
        $t->same(['print'], $field['widgets'][0]['annotation_flag_names']);

        foreach ([
            'emptykids.decoy',
            'mismatchkids.decoy',
            'malformedkids.decoy',
            'Explicit empty Kids decoy label',
            'Mismatched Kids decoy label',
            'Malformed Kids decoy label',
            'empty Kids decoy value',
            'mismatched Kids decoy value',
            'malformed Kids decoy value',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm null Kids parent body', trim($visibleText));
        $t->true(!str_contains($visibleText, 'nullkids@example.test'));
        $t->true(!str_contains($visibleText, 'draft-nullkids@example.test'));
        $t->true(!str_contains($visibleText, 'Null Kids email label'));
    },
];
