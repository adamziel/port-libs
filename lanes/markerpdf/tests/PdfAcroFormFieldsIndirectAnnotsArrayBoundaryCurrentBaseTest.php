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

$indirectAnnotsArrayChainPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect Annots array chain body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots 20 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (chain.parent) /TU (Indirect Annots parent label) /TM (chain-parent-export) /V (parent chain value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (wrong.chain.parent) /V (wrong page chain value must not surface) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 40 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n21 0 R\nendobj\n"
        . "21 0 obj\n[8 0 R << /Subtype /Widget /FT /Tx /T (chain.inline) /TU (Inline chain label) /TM (chain-inline-export) /V (direct chain widget value) /Rect [72 600 320 624] /P 3 0 R /F 4 >> 12 0 R]\nendobj\n"
        . "40 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves indirect page Annots array chains before AcroForm page widget repair' => static function (
        TestRunner $t
    ) use ($indirectAnnotsArrayChainPdf, $fieldsByName): void {
        $pdf = $indirectAnnotsArrayChainPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['chain.parent', 'chain.inline'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $parent = $fields['chain.parent'];
        $t->same(6, $parent['object']);
        $t->same('text', $parent['field_type_label']);
        $t->same('parent chain value', $parent['value']);
        $t->same('Indirect Annots parent label', $parent['alternate_name']);
        $t->same('chain-parent-export', $parent['mapping_name']);
        $t->same([8], array_column($parent['widgets'], 'object'));
        $t->same([0], array_column($parent['widgets'], 'page_index'));
        $t->same([3], array_column($parent['widgets'], 'page_object'));
        $t->same([0], array_column($parent['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($parent['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $parent['widgets'][0]['rect']);

        $inline = $fields['chain.inline'];
        $t->true(is_int($inline['object']) && $inline['object'] > 40);
        $t->same($inline['object'], $inline['widgets'][0]['object']);
        $t->same('text', $inline['field_type_label']);
        $t->same('direct chain widget value', $inline['value']);
        $t->same('Inline chain label', $inline['alternate_name']);
        $t->same('chain-inline-export', $inline['mapping_name']);
        $t->same([1], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 600.0, 320.0, 624.0], $inline['widgets'][0]['rect']);

        foreach ([
            'wrong.chain.parent',
            'wrong page chain value must not surface',
        ] as $wrongPageText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $wrongPageText));
            $t->true(!str_contains($visibleText, $wrongPageText));
        }

        foreach ([
            'parent chain value',
            'direct chain widget value',
            'Indirect Annots parent label',
            'Inline chain label',
        ] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm indirect Annots array chain body', $visibleText);
    },
];
