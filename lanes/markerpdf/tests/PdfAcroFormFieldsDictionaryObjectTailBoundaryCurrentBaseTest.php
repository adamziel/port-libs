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

$tailedFieldDictionaryObjectPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm field dictionary object tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (tailed.field.object) /TU (Tailed field label) /TM (tailed-field-export) /V (Tailed field value must not surface) /Kids [8 0 R] >> << /FT /Tx /T (tailed.field.sibling.decoy) /V (Sibling field decoy value) >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (valid.page.repair) /TU (Valid page repair label) /TM (valid-page-repair-export) /V (Valid page repair value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

$tailedWidgetDictionaryObjectPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget dictionary object tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (tailed.widget.parent) /TU (Tailed widget parent label) /TM (tailed-widget-parent-export) /V (Tailed widget parent value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >> 99 0 R\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (valid.comment.widget) /TU (Valid comment widget label) /TM (valid-comment-widget-export) /V (Valid comment widget value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >> % comment-only widget tail\nendobj\n"
        . "99 0 obj\n<< /Subtype /Widget /Parent 6 0 R /FT /Tx /T (tailed.widget.sibling.decoy) /V (Sibling widget decoy value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed indirect AcroForm field dictionary objects before page-widget repair' => static function (
        TestRunner $t
    ) use ($tailedFieldDictionaryObjectPdf, $fieldsByName): void {
        $pdf = $tailedFieldDictionaryObjectPdf();
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
        $t->same([1], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'tailed.field.object',
            'Tailed field label',
            'tailed-field-export',
            'Tailed field value must not surface',
            'tailed.field.sibling.decoy',
            'Sibling field decoy value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm field dictionary object tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Valid page repair value'));
        $t->same(false, str_contains($visibleText, 'Valid page repair label'));
    },
    'rejects tailed widget dictionary objects while preserving parent field review and comment-only widgets' => static function (
        TestRunner $t
    ) use ($tailedWidgetDictionaryObjectPdf, $fieldsByName): void {
        $pdf = $tailedWidgetDictionaryObjectPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['tailed.widget.parent', 'valid.comment.widget'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $parent = $fields['tailed.widget.parent'];
        $t->same(6, $parent['object']);
        $t->same('text', $parent['field_type_label']);
        $t->same('Tailed widget parent value', $parent['value']);
        $t->same('Tailed widget parent label', $parent['alternate_name']);
        $t->same('tailed-widget-parent-export', $parent['mapping_name']);
        $t->same([], $parent['widgets']);

        $field = $fields['valid.comment.widget'];
        $t->same(10, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Valid comment widget value', $field['value']);
        $t->same('Valid comment widget label', $field['alternate_name']);
        $t->same('valid-comment-widget-export', $field['mapping_name']);
        $t->same([12], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([1], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 600.0, 320.0, 624.0], $field['widgets'][0]['rect']);

        foreach ([
            'tailed.widget.sibling.decoy',
            'Sibling widget decoy value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm widget dictionary object tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Tailed widget parent value'));
        $t->same(false, str_contains($visibleText, 'Tailed widget parent label'));
        $t->same(false, str_contains($visibleText, 'Valid comment widget value'));
        $t->same(false, str_contains($visibleText, 'Valid comment widget label'));
    },
];
