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

$duplicateAnnotsWidgetBoundaryPdf = static function (): string {
    $pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate Annots widget page one body) Tj ET';
    $pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate Annots widget page two body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [12 0 R 14 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Annots [14 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (duplicate.annots.title) /TU (Duplicate Annots title label) /TM (duplicate-annots-title-export) /V (Duplicate Annots title value) /Kids [12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (duplicate.annots.summary) /TU (Duplicate Annots summary label) /TM (duplicate-annots-summary-export) /V (Duplicate Annots summary value) /Kids [14 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps first duplicate page Annots widget occurrence before AcroForm field repair' => static function (
        TestRunner $t
    ) use ($duplicateAnnotsWidgetBoundaryPdf, $fieldsByName): void {
        $pdf = $duplicateAnnotsWidgetBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['duplicate.annots.title', 'duplicate.annots.summary'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $title = $fields['duplicate.annots.title'];
        $t->same(6, $title['object']);
        $t->same('Duplicate Annots title value', $title['value']);
        $t->same('Duplicate Annots title label', $title['alternate_name']);
        $t->same('duplicate-annots-title-export', $title['mapping_name']);
        $t->same([12], array_column($title['widgets'], 'object'));
        $t->same([0], array_column($title['widgets'], 'page_index'));
        $t->same([3], array_column($title['widgets'], 'page_object'));
        $t->same([0], array_column($title['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($title['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $title['widgets'][0]['rect']);

        $summary = $fields['duplicate.annots.summary'];
        $t->same(10, $summary['object']);
        $t->same('Duplicate Annots summary value', $summary['value']);
        $t->same('Duplicate Annots summary label', $summary['alternate_name']);
        $t->same('duplicate-annots-summary-export', $summary['mapping_name']);
        $t->same([14], array_column($summary['widgets'], 'object'));
        $t->same([0], array_column($summary['widgets'], 'page_index'));
        $t->same([3], array_column($summary['widgets'], 'page_object'));
        $t->same([1], array_column($summary['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($summary['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 600.0, 320.0, 624.0], $summary['widgets'][0]['rect']);

        $t->true(str_contains($visibleText, 'Visible duplicate Annots widget page one body'));
        $t->true(str_contains($visibleText, 'Visible duplicate Annots widget page two body'));
        foreach ([
            'Duplicate Annots title value',
            'Duplicate Annots summary value',
            'Duplicate Annots title label',
            'Duplicate Annots summary label',
        ] as $reviewOnlyText) {
            $t->true(is_string($encoded) && str_contains($encoded, $reviewOnlyText));
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }
    },
];
