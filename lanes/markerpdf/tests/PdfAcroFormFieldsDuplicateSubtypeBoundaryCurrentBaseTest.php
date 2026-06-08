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

$duplicateWidgetSubtypeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate widget Subtype boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.duplicate_subtype) /TU (Duplicate Subtype label) /TM (duplicate-subtype-export) /V (Duplicate Subtype value) /Kids [8 0 R 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Text /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Subtype /Text /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 /Contents (Stale widget subtype annotation) >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Text /Subtype /Widget /FT /Tx /T (inline.duplicate_subtype) /TU (Inline duplicate Subtype label) /TM (inline-duplicate-subtype-export) /V (Inline duplicate Subtype value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Subtype /Text /FT /Tx /T (stale.inline_subtype) /TU (Stale inline Subtype label) /TM (stale-inline-subtype-export) /V (Stale inline Subtype value must not surface) /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last duplicate Widget Subtype before AcroForm field boundary repair' => static function (
        TestRunner $t
    ) use ($duplicateWidgetSubtypeBoundaryPdf, $fieldsByName): void {
        $pdf = $duplicateWidgetSubtypeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.duplicate_subtype', 'inline.duplicate_subtype'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['article.duplicate_subtype'];
        $t->same(6, $listed['object']);
        $t->same('article.duplicate_subtype', $listed['name']);
        $t->same('Duplicate Subtype label', $listed['alternate_name']);
        $t->same('duplicate-subtype-export', $listed['mapping_name']);
        $t->same('text', $listed['field_type_label']);
        $t->same('Duplicate Subtype value', $listed['value']);
        $t->same(['FT', 'V'], $listed['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $listed['field_hierarchy']['inherited_attributes']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));
        $t->same([[72.0, 640.0, 320.0, 664.0]], array_column($listed['widgets'], 'rect'));

        $inline = $fields['inline.duplicate_subtype'];
        $t->same(12, $inline['object']);
        $t->same('inline.duplicate_subtype', $inline['name']);
        $t->same('Inline duplicate Subtype label', $inline['alternate_name']);
        $t->same('inline-duplicate-subtype-export', $inline['mapping_name']);
        $t->same('text', $inline['field_type_label']);
        $t->same('Inline duplicate Subtype value', $inline['value']);
        $t->same([12], array_column($inline['widgets'], 'object'));
        $t->same([0], array_column($inline['widgets'], 'page_index'));
        $t->same([3], array_column($inline['widgets'], 'page_object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));
        $t->same([[72.0, 560.0, 320.0, 584.0]], array_column($inline['widgets'], 'rect'));

        foreach ([
            'Stale widget subtype annotation',
            'stale.inline_subtype',
            'Stale inline Subtype label',
            'stale-inline-subtype-export',
            'Stale inline Subtype value must not surface',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        foreach ([
            'Duplicate Subtype value',
            'Inline duplicate Subtype value',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm duplicate widget Subtype boundary body', $visibleText);
    },
];
