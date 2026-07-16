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

$tailedAcroFormRootDefaultsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root default tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 41 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/TailFont 9 Tf 1 0 0 rg) 90 0 R /DR 30 0 R 91 0 R /Q 2 92 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (defaults.tailed) /TU (Tailed root default label) /TM (tailed-root-default-export) /V (Tailed root default value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Font << /TailFont 40 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "90 0 obj\n(/Tail default appearance operand must not surface)\nendobj\n"
        . "91 0 obj\n<< /Font << /TailDecoy 42 0 R >> >>\nendobj\n"
        . "92 0 obj\n0\nendobj\n"
        . "%%EOF";
};

$commentOnlyAcroFormRootDefaultsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root default comment body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 41 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/SafeFont 10 Tf 0 0 1 rg) % root DA comment tail\n/DR 30 0 R % root DR comment tail\n/Q 1 % root Q comment tail\n>>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (defaults.comment) /TU (Comment root default label) /TM (comment-root-default-export) /V (Comment root default value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Font << /SafeFont 40 0 R >> >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed AcroForm root defaults before inherited field appearance review' => static function (
        TestRunner $t
    ) use ($tailedAcroFormRootDefaultsPdf, $fieldsByName): void {
        $pdf = $tailedAcroFormRootDefaultsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(true, $form['need_appearances']);
        $t->same(['defaults.tailed'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(['source' => null, 'object' => null, 'font_count' => 0, 'fonts' => []], [
            'source' => $form['default_resources']['source'],
            'object' => $form['default_resources']['object'],
            'font_count' => $form['default_resources']['font_count'],
            'fonts' => $form['default_resources']['fonts'],
        ]);

        $field = $fields['defaults.tailed'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Tailed root default value', $field['value']);
        $t->same(null, $field['default_appearance']);
        $t->same(null, $field['quadding']);
        $t->same(null, $field['text_alignment']);
        $t->same(false, $field['quadding_review']['present']);
        $t->same(null, $field['quadding_review']['quadding_source_boundary']);
        $t->same([], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V'], $field['field_hierarchy']['local_attributes']);
        $t->same(['V'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));

        foreach ([
            'TailFont',
            'TailDecoy',
            'Courier',
            '/TailFont 9 Tf',
            'Tail default appearance operand must not surface',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm root default tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Tailed root default value'));
        $t->same(false, str_contains($visibleText, 'Tailed root default label'));
    },
    'keeps comment-only AcroForm root defaults usable for inherited appearance review' => static function (
        TestRunner $t
    ) use ($commentOnlyAcroFormRootDefaultsPdf, $fieldsByName): void {
        $pdf = $commentOnlyAcroFormRootDefaultsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(true, $form['need_appearances']);
        $t->same(['defaults.comment'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same('acroform', $form['default_resources']['source']);
        $t->same(30, $form['default_resources']['object']);
        $t->same(['SafeFont'], array_keys($form['default_resources']['fonts']));
        $t->same('Helvetica', $form['default_resources']['fonts']['SafeFont']['base_font']);

        $field = $fields['defaults.comment'];
        $t->same(6, $field['object']);
        $t->same('Comment root default value', $field['value']);
        $t->same(['DA', 'DR', 'Q'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V'], $field['field_hierarchy']['local_attributes']);
        $t->same(1, $field['quadding']);
        $t->same('center', $field['text_alignment']);
        $t->same('acroform_default', $field['quadding_review']['quadding_source_boundary']);

        $appearance = $field['default_appearance'];
        $t->same('acroform', $appearance['source']);
        $t->same(null, $appearance['source_object']);
        $t->same('SafeFont', $appearance['font_resource']);
        $t->same(10.0, $appearance['font_size']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.0, 0.0, 1.0]], $appearance['text_color']);
        $t->same(true, $appearance['font_resource_resolved']);
        $t->same(40, $appearance['font_resource_object']);
        $t->same('Helvetica', $appearance['font_resource_base_font']);

        $t->same('Visible AcroForm root default comment body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Comment root default value'));
        $t->same(false, str_contains($visibleText, 'Comment root default label'));
    },
];
