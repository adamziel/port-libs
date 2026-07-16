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

$tailedScalarObjectPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm scalar object tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (safe.scalar) /TU 30 0 R /TM 31 0 R /V 32 0 R /DV 33 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (safe.choice) /V (publish) /Opt [[34 0 R 35 0 R] [(publish) (Publish)]] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T 36 0 R /TM (safe.unnamed.export) /V (Direct safe unnamed value) /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n(Tailed alternate label must not surface) 99 0 R\nendobj\n"
        . "31 0 obj\n(tailed-mapping-must-not-surface) /Bad\nendobj\n"
        . "32 0 obj\n(Tailed current value must not surface) 77\nendobj\n"
        . "33 0 obj\n(Tailed default value must not surface) false\nendobj\n"
        . "34 0 obj\n(draft-tailed-export-must-not-surface) 123\nendobj\n"
        . "35 0 obj\n(Draft tailed label must not surface) /Extra\nendobj\n"
        . "36 0 obj\n(tailed.partial.name.must.not.surface) 50 0 R\nendobj\n"
        . "%%EOF";
};

return [
    'rejects current-generation AcroForm scalar objects with trailing operands before field review' => static function (
        TestRunner $t
    ) use ($tailedScalarObjectPdf, $fieldsByName): void {
        $pdf = $tailedScalarObjectPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['safe.scalar', 'safe.choice', '#14'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $scalar = $fields['safe.scalar'];
        $t->same(6, $scalar['object']);
        $t->same('text', $scalar['field_type_label']);
        $t->same(null, $scalar['alternate_name']);
        $t->same('safe.scalar', $scalar['mapping_name']);
        $t->same(null, $scalar['value']);
        $t->same(null, $scalar['default_value']);
        $t->same(true, $scalar['value_state']['has_current_value']);
        $t->same(true, $scalar['value_state']['has_default_value']);
        $t->same(null, $scalar['value_state']['current']);
        $t->same(null, $scalar['value_state']['default']);
        $t->same([8], array_column($scalar['widgets'], 'object'));
        $t->same([0], array_column($scalar['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($scalar['widgets'], 'referenced_from_page_annots'));

        $choice = $fields['safe.choice'];
        $t->same(10, $choice['object']);
        $t->same('choice', $choice['field_type_label']);
        $t->same('publish', $choice['value']);
        $t->same([['export' => 'publish', 'label' => 'Publish']], $choice['options']);
        $t->same([12], array_column($choice['widgets'], 'object'));

        $unnamed = $fields['#14'];
        $t->same(14, $unnamed['object']);
        $t->same('#14', $unnamed['name']);
        $t->same(null, $unnamed['partial_name']);
        $t->same('safe.unnamed.export', $unnamed['mapping_name']);
        $t->same('Direct safe unnamed value', $unnamed['value']);
        $t->same([16], array_column($unnamed['widgets'], 'object'));

        foreach ([
            'Tailed alternate label must not surface',
            'tailed-mapping-must-not-surface',
            'Tailed current value must not surface',
            'Tailed default value must not surface',
            'draft-tailed-export-must-not-surface',
            'Draft tailed label must not surface',
            'tailed.partial.name.must.not.surface',
        ] as $tailedText) {
            $t->same(false, str_contains($encoded, $tailedText));
            $t->same(false, str_contains($visibleText, $tailedText));
        }

        $t->same('Visible AcroForm scalar object tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Direct safe unnamed value'));
        $t->same(false, str_contains($visibleText, 'publish'));
    },
];
