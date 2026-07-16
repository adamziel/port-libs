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

$duplicateFullNameBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate full-name boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /TU (Stale duplicate title label) /TM (stale-title-export) /V (stale title value must not surface) /DV (stale title draft) /MaxLen 12 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article.title) /TU (Current duplicate title label) /TM (current-title-export) /V (current title value) /DV (current title draft) /MaxLen 96 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'deduplicates AcroForm terminal fields by fully qualified name before WordPress review' => static function (
        TestRunner $t
    ) use ($duplicateFullNameBoundaryPdf, $fieldsByName): void {
        $pdf = $duplicateFullNameBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.title'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.title'];
        $t->same(10, $field['object']);
        $t->same('article.title', $field['name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Current duplicate title label', $field['alternate_name']);
        $t->same('current-title-export', $field['mapping_name']);
        $t->same('current title value', $field['value']);
        $t->same('current title draft', $field['default_value']);
        $t->same(96, $field['max_length']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(10, $field['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same([12], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([1], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'Stale duplicate title label',
            'stale-title-export',
            'stale title value must not surface',
            'stale title draft',
        ] as $staleText) {
            $t->same(false, str_contains($encoded, $staleText));
            $t->same(false, str_contains($visibleText, $staleText));
        }

        $t->same('Visible AcroForm duplicate full-name boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'current title value'));
        $t->same(false, str_contains($visibleText, 'current title draft'));
        $t->same(false, str_contains($visibleText, 'Current duplicate title label'));
    },
];
