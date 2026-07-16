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

$listedKidsPageWidgetBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm listed Kids page-widget boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.kids) /TU (Listed Kids label) /TM (listed-kids-export) /V (Listed Kids value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 /TU (Stray page widget label must not attach) >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx /T (empty.kids) /TU (Empty Kids label) /TM (empty-kids-export) /V (Empty Kids value) /Kids [] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 /TU (Empty Kids stray label must not attach) >>\nendobj\n"
        . "%%EOF";
};

return [
    'does not repair listed AcroForm fields with page widgets outside explicit Kids trees' => static function (
        TestRunner $t
    ) use ($listedKidsPageWidgetBoundaryPdf, $fieldsByName): void {
        $pdf = $listedKidsPageWidgetBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.kids', 'empty.kids'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.kids'];
        $t->same(6, $listed['object']);
        $t->same('text', $listed['field_type_label']);
        $t->same('Listed Kids value', $listed['value']);
        $t->same('Listed Kids label', $listed['alternate_name']);
        $t->same('listed-kids-export', $listed['mapping_name']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));

        $empty = $fields['empty.kids'];
        $t->same(16, $empty['object']);
        $t->same('text', $empty['field_type_label']);
        $t->same('Empty Kids value', $empty['value']);
        $t->same('Empty Kids label', $empty['alternate_name']);
        $t->same('empty-kids-export', $empty['mapping_name']);
        $t->same([], $empty['widgets']);
        $t->same(['FT', 'V'], $empty['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $empty['field_hierarchy']['inherited_attributes']);

        foreach ([
            'Stray page widget label must not attach',
            'Empty Kids stray label must not attach',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm listed Kids page-widget boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Listed Kids value'));
        $t->same(false, str_contains($visibleText, 'Empty Kids value'));
        $t->same(false, $listed['field_name_review']['field_value_used_as_visible_text']);
        $t->same(false, $empty['field_name_review']['alternate_name_used_as_visible_text']);
        $t->same(false, $empty['field_hierarchy']['executes_form_actions']);
        $t->same(false, $empty['field_hierarchy']['executes_javascript']);
    },
];
