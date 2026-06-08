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

$tailedDirectFieldsArrayPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Fields array tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] 90 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (tailed.direct.fields.decoy) /TU (Tailed direct Fields label) /TM (tailed-direct-fields-export) /V (Tailed direct Fields value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (valid.page.repair) /TU (Valid page repair label) /TM (valid-page-repair-export) /V (Valid page repair value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "90 0 obj\n<< /FT /Tx /T (tailed.direct.fields.tail.decoy) /V (Trailing direct Fields operand value) >>\nendobj\n"
        . "%%EOF";
};

$tailedDirectKidsArrayPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Kids array tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (valid.parent) /TU (Valid parent label) /TM (valid-parent-export) /V (Valid parent value) /DV (Valid parent default) /MaxLen 48 /Kids [10 0 R] 90 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Parent 6 0 R /T (malformed.direct.kids.decoy) /TU (Malformed direct Kids label) /TM (malformed-direct-kids-export) /V (Malformed direct Kids value) >>\nendobj\n"
        . "90 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects direct AcroForm Fields arrays with trailing operands before page-widget repair' => static function (
        TestRunner $t
    ) use ($tailedDirectFieldsArrayPdf, $fieldsByName): void {
        $pdf = $tailedDirectFieldsArrayPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['valid.page.repair'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['valid.page.repair'];
        $t->same(10, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Valid page repair value', $field['value']);
        $t->same('Valid page repair label', $field['alternate_name']);
        $t->same('valid-page-repair-export', $field['mapping_name']);
        $t->same([12], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'tailed.direct.fields.decoy',
            'Tailed direct Fields label',
            'tailed-direct-fields-export',
            'Tailed direct Fields value',
            'tailed.direct.fields.tail.decoy',
            'Trailing direct Fields operand value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm direct Fields array tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Valid page repair value'));
        $t->same(false, str_contains($visibleText, 'Valid page repair label'));
    },
    'rejects direct AcroForm Kids arrays with trailing operands before terminal field review' => static function (
        TestRunner $t
    ) use ($tailedDirectKidsArrayPdf, $fieldsByName): void {
        $pdf = $tailedDirectKidsArrayPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['valid.parent'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['valid.parent'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Valid parent value', $field['value']);
        $t->same('Valid parent default', $field['default_value']);
        $t->same('Valid parent label', $field['alternate_name']);
        $t->same('valid-parent-export', $field['mapping_name']);
        $t->same(48, $field['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same([], $field['field_hierarchy']['ancestor_objects']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'valid.parent.malformed.direct.kids.decoy',
            'malformed.direct.kids.decoy',
            'Malformed direct Kids label',
            'malformed-direct-kids-export',
            'Malformed direct Kids value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm direct Kids array tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Valid parent value'));
        $t->same(false, str_contains($visibleText, 'Valid parent default'));
        $t->same(false, str_contains($visibleText, 'Valid parent label'));
    },
];
