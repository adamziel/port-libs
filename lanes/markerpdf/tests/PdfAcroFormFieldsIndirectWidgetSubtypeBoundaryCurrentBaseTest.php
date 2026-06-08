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

$indirectWidgetSubtypeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect Widget subtype boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 22 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.indirect.widget) /TU (Listed indirect Widget label) /TM (listed-indirect-widget-export) /V (Listed indirect Widget value) /DV (Listed indirect Widget default) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype 30 1 R /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (page.indirect.widget) /TU (Page repair indirect Widget label) /TM (page-indirect-widget-export) /V (Page repair indirect Widget value) /DV (Page repair indirect Widget default) /MaxLen 48 >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype 30 1 R /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Type /Annot /Subtype 31 0 R /FT /Tx /T (text.annotation.decoy) /V (Text annotation decoy value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (stale.indirect.widget.parent) /V (Stale indirect Widget parent value) >>\nendobj\n"
        . "22 0 obj\n<< /Type /Annot /Subtype 32 0 R /Parent 20 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n/Widget\nendobj\n"
        . "30 0 obj\n/Link\nendobj\n"
        . "31 0 obj\n/Text\nendobj\n"
        . "32 1 obj\n/Widget\nendobj\n"
        . "32 0 obj\n/Link\nendobj\n"
        . "%%EOF";
};

return [
    'resolves generation-exact indirect Widget subtype names before AcroForm field review' => static function (
        TestRunner $t
    ) use ($indirectWidgetSubtypeBoundaryPdf, $fieldsByName): void {
        $pdf = $indirectWidgetSubtypeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.indirect.widget', 'page.indirect.widget'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.indirect.widget'];
        $t->same(6, $listed['object']);
        $t->same('text', $listed['field_type_label']);
        $t->same('Listed indirect Widget label', $listed['alternate_name']);
        $t->same('listed-indirect-widget-export', $listed['mapping_name']);
        $t->same('Listed indirect Widget value', $listed['value']);
        $t->same('Listed indirect Widget default', $listed['default_value']);
        $t->same(64, $listed['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $listed['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $listed['field_hierarchy']['inherited_attributes']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));
        $t->same([[72.0, 640.0, 320.0, 664.0]], array_column($listed['widgets'], 'rect'));

        $pageRepair = $fields['page.indirect.widget'];
        $t->same(10, $pageRepair['object']);
        $t->same('text', $pageRepair['field_type_label']);
        $t->same('Page repair indirect Widget label', $pageRepair['alternate_name']);
        $t->same('page-indirect-widget-export', $pageRepair['mapping_name']);
        $t->same('Page repair indirect Widget value', $pageRepair['value']);
        $t->same('Page repair indirect Widget default', $pageRepair['default_value']);
        $t->same(48, $pageRepair['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $pageRepair['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $pageRepair['field_hierarchy']['inherited_attributes']);
        $t->same([12], array_column($pageRepair['widgets'], 'object'));
        $t->same([0], array_column($pageRepair['widgets'], 'page_index'));
        $t->same([3], array_column($pageRepair['widgets'], 'page_object'));
        $t->same([1], array_column($pageRepair['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($pageRepair['widgets'], 'referenced_from_page_annots'));
        $t->same([[72.0, 600.0, 320.0, 624.0]], array_column($pageRepair['widgets'], 'rect'));

        foreach ([
            'text.annotation.decoy',
            'Text annotation decoy value',
            'stale.indirect.widget.parent',
            'Stale indirect Widget parent value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        foreach ([
            'Listed indirect Widget value',
            'Listed indirect Widget default',
            'Listed indirect Widget label',
            'Page repair indirect Widget value',
            'Page repair indirect Widget default',
            'Page repair indirect Widget label',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm indirect Widget subtype boundary body', $visibleText);
    },
];
