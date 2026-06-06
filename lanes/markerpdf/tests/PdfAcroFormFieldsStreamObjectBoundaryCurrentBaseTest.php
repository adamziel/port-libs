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

$acroFormStreamObjectBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm stream object boundary body) Tj ET';
    $fieldStream = 'BT /F1 12 Tf 72 680 Td (Stream field payload leak) Tj ET';
    $widgetStream = 'BT /F1 12 Tf 72 660 Td (Stream widget payload leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (stream.root.decoy) /V (Stream root value) /Kids [8 0 R] /Length " . strlen($fieldStream) . " >>\nstream\n{$fieldStream}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (normal.field) /V (Normal value) /Kids [12 0 R 14 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /FT /Tx /T (stream.inline.decoy) /V (Stream inline value) /Rect [72 520 320 544] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects stream objects as AcroForm field and widget dictionaries before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormStreamObjectBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormStreamObjectBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['normal.field'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['normal.field'];
        $t->same(10, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Normal value', $field['value']);
        $t->same([12], array_column($field['widgets'], 'object'));
        $t->same([1], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 600.0, 320.0, 624.0], $field['widgets'][0]['rect']);

        foreach (['stream.root.decoy', 'stream.inline.decoy'] as $decoyName) {
            $t->true(!isset($fields[$decoyName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyName));
        }

        foreach ([
            'Stream root value',
            'Stream inline value',
            'Stream field payload leak',
            'Stream widget payload leak',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm stream object boundary body'));
        $t->true(!str_contains($visibleText, 'Normal value'));
    },
];
