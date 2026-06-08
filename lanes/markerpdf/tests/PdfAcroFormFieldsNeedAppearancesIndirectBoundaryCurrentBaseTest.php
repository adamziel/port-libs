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

$needAppearancesBoundaryPdf = static function (string $needAppearancesValue, string $needAppearancesObjectBody): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect NeedAppearances body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances {$needAppearancesValue} /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (needappearances.indirect) /TU (Indirect NeedAppearances label) /TM (needappearances-export) /V (Indirect NeedAppearances value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n{$needAppearancesObjectBody}\nendobj\n"
        . "%%EOF";
};

return [
    'resolves indirect true NeedAppearances before AcroForm field review' => static function (
        TestRunner $t
    ) use ($needAppearancesBoundaryPdf, $fieldsByName): void {
        $pdf = $needAppearancesBoundaryPdf('30 0 R', 'true');
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(true, $form['need_appearances']);
        $t->same(['needappearances.indirect'], array_keys($fields));
        $t->same(1, count($form['fields']));

        $field = $fields['needappearances.indirect'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Indirect NeedAppearances value', $field['value']);
        $t->same('Indirect NeedAppearances label', $field['alternate_name']);
        $t->same('needappearances-export', $field['mapping_name']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same(false, str_contains($visibleText, 'Indirect NeedAppearances value'));
        $t->same('Visible AcroForm indirect NeedAppearances body', $visibleText);
    },
    'resolves indirect false NeedAppearances without promoting form values to text' => static function (
        TestRunner $t
    ) use ($needAppearancesBoundaryPdf, $fieldsByName): void {
        $pdf = $needAppearancesBoundaryPdf('30 0 R', 'false');
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(false, $form['need_appearances']);
        $t->same(['needappearances.indirect'], array_keys($fields));
        $t->same('Indirect NeedAppearances value', $fields['needappearances.indirect']['value']);
        $t->same([8], array_column($fields['needappearances.indirect']['widgets'], 'object'));
        $t->same(false, str_contains($visibleText, 'Indirect NeedAppearances value'));
        $t->same('Visible AcroForm indirect NeedAppearances body', $visibleText);
    },
    'rejects tailed indirect NeedAppearances booleans before WordPress form review' => static function (
        TestRunner $t
    ) use ($needAppearancesBoundaryPdf, $fieldsByName): void {
        $pdf = $needAppearancesBoundaryPdf('30 0 R', 'true /BadOperand');
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(false, $form['need_appearances']);
        $t->same(['needappearances.indirect'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same('Indirect NeedAppearances value', $fields['needappearances.indirect']['value']);
        $t->same([8], array_column($fields['needappearances.indirect']['widgets'], 'object'));
        $t->same(false, str_contains($encoded, 'BadOperand'));
        $t->same(false, str_contains($visibleText, 'Indirect NeedAppearances value'));
        $t->same('Visible AcroForm indirect NeedAppearances body', $visibleText);
    },
];
