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

$duplicateCatalogAcroFormPointerPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate catalog AcroForm pointer body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 50 0 R /Acr#6FForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (current.catalog.email) /TU (Current catalog email label) /TM (current-catalog-email-export) /V (current-catalog@example.test) /DV (draft-catalog@example.test) /MaxLen 96 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances false /DA (/Stale 7 Tf 1 0 0 rg) >>\nendobj\n"
        . "52 0 obj\n<< /FT /Tx /T (stale.catalog.email) /TU (Stale catalog label) /TM (stale-catalog-export) /V (stale-catalog@example.test) >>\nendobj\n"
        . "%%EOF";
};

$duplicateCatalogDirectAcroFormPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate catalog direct AcroForm body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 50 0 R /AcroForm << /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 1 rg) >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (current.direct.catalog) /TU (Current direct catalog label) /TM (current-direct-catalog-export) /V (current direct catalog value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances false /DA (/Stale 7 Tf 1 0 0 rg) >>\nendobj\n"
        . "52 0 obj\n<< /FT /Tx /T (stale.direct.catalog) /TU (Stale direct catalog label) /V (stale direct catalog value) >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last catalog AcroForm pointer before WordPress field review' => static function (
        TestRunner $t
    ) use ($duplicateCatalogAcroFormPointerPdf, $fieldsByName): void {
        $pdf = $duplicateCatalogAcroFormPointerPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.catalog.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['current.catalog.email'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('current-catalog@example.test', $field['value']);
        $t->same('draft-catalog@example.test', $field['default_value']);
        $t->same('Current catalog email label', $field['alternate_name']);
        $t->same('current-catalog-email-export', $field['mapping_name']);
        $t->same(96, $field['max_length']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'stale.catalog.email',
            'Stale catalog label',
            'stale-catalog-export',
            'stale-catalog@example.test',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible duplicate catalog AcroForm pointer body', $visibleText);
        $t->same(false, str_contains($visibleText, 'current-catalog@example.test'));
        $t->same(false, str_contains($visibleText, 'Current catalog email label'));
    },
    'uses the last direct catalog AcroForm dictionary before stale pointer metadata' => static function (
        TestRunner $t
    ) use ($duplicateCatalogDirectAcroFormPdf, $fieldsByName): void {
        $pdf = $duplicateCatalogDirectAcroFormPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.direct.catalog'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['current.direct.catalog'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('current direct catalog value', $field['value']);
        $t->same('Current direct catalog label', $field['alternate_name']);
        $t->same('current-direct-catalog-export', $field['mapping_name']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'stale.direct.catalog',
            'Stale direct catalog label',
            'stale direct catalog value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible duplicate catalog direct AcroForm body', $visibleText);
        $t->same(false, str_contains($visibleText, 'current direct catalog value'));
        $t->same(false, str_contains($visibleText, 'Current direct catalog label'));
    },
];
