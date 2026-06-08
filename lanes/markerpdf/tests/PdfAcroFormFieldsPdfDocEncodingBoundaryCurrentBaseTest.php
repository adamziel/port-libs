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

$acroFormPdfDocEncodingFieldStringsPdf = static function (): string {
    $bullet = chr(0x80);
    $dagger = chr(0x81);
    $doubleDagger = chr(0x82);
    $ellipsis = chr(0x83);
    $leftQuote = chr(0x8d);
    $rightQuote = chr(0x8e);
    $fi = chr(0x93);
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm PDFDocEncoding body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (workflow{$bullet}title) /TU (Title {$leftQuote}PDF{$rightQuote} label) "
        . "/TM (workflow{$bullet}title{$dagger}export) /V (Draft{$dagger} value) /DV (Default{$fi} value) "
        . "/MaxLen 48 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (workflow{$dagger}status) /V (publish{$bullet}state) "
        . "/DV (draft{$dagger}state) /Opt [[(draft{$dagger}state) (Draft{$doubleDagger} label)] "
        . "[(publish{$bullet}state) (Publish{$ellipsis} label)]] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'decodes PDFDocEncoding AcroForm text strings before WordPress field review' => static function (
        TestRunner $t
    ) use ($acroFormPdfDocEncodingFieldStringsPdf, $fieldsByName): void {
        $pdf = $acroFormPdfDocEncodingFieldStringsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $titleName = "workflow\u{2022}title";
        $statusName = "workflow\u{2020}status";
        $t->same([$titleName, $statusName], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);
        $t->true(is_string($encoded));

        $title = $fields[$titleName];
        $t->same(6, $title['object']);
        $t->same($titleName, $title['partial_name']);
        $t->same('text', $title['field_type_label']);
        $t->same("Title \u{201C}PDF\u{201D} label", $title['alternate_name']);
        $t->same("workflow\u{2022}title\u{2020}export", $title['mapping_name']);
        $t->same("Draft\u{2020} value", $title['value']);
        $t->same("Default\u{FB01} value", $title['default_value']);
        $t->same(48, $title['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $title['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $title['field_hierarchy']['inherited_attributes']);
        $t->same([8], array_column($title['widgets'], 'object'));
        $t->same([0], array_column($title['widgets'], 'page_index'));
        $t->same([3], array_column($title['widgets'], 'page_object'));
        $t->same([0], array_column($title['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($title['widgets'], 'referenced_from_page_annots'));

        $status = $fields[$statusName];
        $t->same(10, $status['object']);
        $t->same('choice', $status['field_type_label']);
        $t->same("publish\u{2022}state", $status['value']);
        $t->same("draft\u{2020}state", $status['default_value']);
        $t->same([
            ['export' => "draft\u{2020}state", 'label' => "Draft\u{2021} label"],
            ['export' => "publish\u{2022}state", 'label' => "Publish\u{2026} label"],
        ], $status['options']);
        $t->same([
            ['index' => 1, 'export' => "publish\u{2022}state", 'label' => "Publish\u{2026} label"],
        ], $status['value_state']['selected_options']);
        $t->same("publish\u{2022}state", $status['value_state']['display_value']);
        $t->same([12], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));

        $t->true(is_string($encoded) && !str_contains($encoded, chr(0x80)));
        $t->same('Visible AcroForm PDFDocEncoding body', $visibleText);
        foreach ([
            "Draft\u{2020} value",
            "Default\u{FB01} value",
            "publish\u{2022}state",
            "Title \u{201C}PDF\u{201D} label",
            "Publish\u{2026} label",
            chr(0x80),
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }
    },
];
