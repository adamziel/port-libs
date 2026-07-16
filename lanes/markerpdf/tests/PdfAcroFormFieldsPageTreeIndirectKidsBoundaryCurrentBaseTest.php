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

$pageTreeIndirectKidsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect page Kids body) Tj ET';
    $staleText = 'BT /F1 12 Tf 72 720 Td (Detached stale AcroForm page body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids 20 0 R /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Contents 31 0 R /Annots [18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [8 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (current.indirectpage) /TU (Current indirect page label) /TM (current-indirect-page-export) /V (current page form value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 4 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx /T (stale.detachedpage) /TU (Stale detached page label) /TM (stale-detached-page-export) /V (stale detached page value must not import) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n[4 0 R]\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'uses indirect page tree Kids arrays before AcroForm page widget repair' => static function (
        TestRunner $t
    ) use ($pageTreeIndirectKidsBoundaryPdf, $fieldsByName): void {
        $pdf = $pageTreeIndirectKidsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.indirectpage'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['current.indirectpage'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('current page form value', $field['value']);
        $t->same('Current indirect page label', $field['alternate_name']);
        $t->same('current-indirect-page-export', $field['mapping_name']);
        $t->same(['FT', 'V'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([4], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        foreach ([
            'stale.detachedpage',
            'Stale detached page label',
            'stale-detached-page-export',
            'stale detached page value must not import',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }

        $t->same('Visible AcroForm indirect page Kids body', $visibleText);
        $t->true(!str_contains($visibleText, 'current page form value'));
        $t->true(!str_contains($visibleText, 'Detached stale AcroForm page body'));
    },
];
