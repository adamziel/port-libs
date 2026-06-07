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

$duplicatePageAnnotsKeyBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate page Annots key AcroForm body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [18 0 R] /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.email) /TU (Listed email label) /TM (listed-email-export) /V (listed@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (current.category) /TU (Current category label) /TM (current-category-export) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /FT /Tx /T (current.inline) /TU (Current inline label) /TM (current-inline-export) /V (inline current value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /FT /Tx /T (stale.first.annots) /TU (Stale first Annots label must not surface) /TM (stale-first-annots-export) /V (stale first Annots value must not surface) /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last top-level page Annots key before AcroForm page widget repair' => static function (
        TestRunner $t
    ) use ($duplicatePageAnnotsKeyBoundaryPdf, $fieldsByName): void {
        $pdf = $duplicatePageAnnotsKeyBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.email', 'current.category', 'current.inline'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.email'];
        $t->same(6, $listed['object']);
        $t->same('listed@example.test', $listed['value']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));

        $category = $fields['current.category'];
        $t->same(10, $category['object']);
        $t->same('choice', $category['field_type_label']);
        $t->same('page', $category['value']);
        $t->same('Current category label', $category['alternate_name']);
        $t->same('current-category-export', $category['mapping_name']);
        $t->same([['export' => 'post', 'label' => 'post'], ['export' => 'page', 'label' => 'page']], $category['options']);
        $t->same([12], array_column($category['widgets'], 'object'));
        $t->same([1], array_column($category['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($category['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $category['value_state']['hierarchy_boundary']['current_value_source']);

        $inline = $fields['current.inline'];
        $t->same(16, $inline['object']);
        $t->same('text', $inline['field_type_label']);
        $t->same('inline current value', $inline['value']);
        $t->same('Current inline label', $inline['alternate_name']);
        $t->same('current-inline-export', $inline['mapping_name']);
        $t->same([16], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'stale.first.annots',
            'Stale first Annots label must not surface',
            'stale-first-annots-export',
            'stale first Annots value must not surface',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }

        foreach ([
            'listed@example.test',
            'inline current value',
            'Current category label',
            'Current inline label',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible duplicate page Annots key AcroForm body', $visibleText);
    },
];
