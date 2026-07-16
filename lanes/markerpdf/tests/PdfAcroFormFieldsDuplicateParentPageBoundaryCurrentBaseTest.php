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

$duplicateParentPageBoundaryPdf = static function (): string {
    $pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate parent page boundary one) Tj ET';
    $pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate parent page boundary two) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (duplicate.parent.current) /TU (Duplicate Parent current label) /TM (duplicate-parent-current-export) /V (Current duplicate Parent value) /DV (Current duplicate Parent draft) /MaxLen 64 >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 99 0 R /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (duplicate.parent.stale-last) /TU (Duplicate Parent stale label) /TM (duplicate-parent-stale-export) /V (Stale duplicate Parent value must not surface) >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Parent 98 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (duplicate.page.current) /TU (Duplicate page current label) /TM (duplicate-page-current-export) /V (Current duplicate page value) >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 4 0 R /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Tx /T (duplicate.page.stale-last) /TU (Duplicate page stale label) /TM (duplicate-page-stale-export) /V (Stale duplicate page value must not surface) >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 520 320 544] /P 3 0 R /P 4 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
        . "98 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (stale.parent.first) /TU (Stale first Parent label) /TM (stale-first-parent-export) /V (Stale first Parent value must not surface) >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses last duplicate Widget Parent and P keys before page-owned AcroForm field repair' => static function (
        TestRunner $t
    ) use ($duplicateParentPageBoundaryPdf, $fieldsByName): void {
        $pdf = $duplicateParentPageBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['duplicate.parent.current', 'duplicate.page.current'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $parent = $fields['duplicate.parent.current'];
        $t->same(6, $parent['object']);
        $t->same('duplicate.parent.current', $parent['name']);
        $t->same('Duplicate Parent current label', $parent['alternate_name']);
        $t->same('duplicate-parent-current-export', $parent['mapping_name']);
        $t->same('text', $parent['field_type_label']);
        $t->same('Current duplicate Parent value', $parent['value']);
        $t->same('Current duplicate Parent draft', $parent['default_value']);
        $t->same(64, $parent['max_length']);
        $t->same([8], array_column($parent['widgets'], 'object'));
        $t->same([0], array_column($parent['widgets'], 'page_index'));
        $t->same([3], array_column($parent['widgets'], 'page_object'));
        $t->same([0], array_column($parent['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($parent['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $parent['widgets'][0]['rect']);

        $page = $fields['duplicate.page.current'];
        $t->same(14, $page['object']);
        $t->same('duplicate.page.current', $page['name']);
        $t->same('Duplicate page current label', $page['alternate_name']);
        $t->same('duplicate-page-current-export', $page['mapping_name']);
        $t->same('text', $page['field_type_label']);
        $t->same('Current duplicate page value', $page['value']);
        $t->same([16], array_column($page['widgets'], 'object'));
        $t->same([0], array_column($page['widgets'], 'page_index'));
        $t->same([3], array_column($page['widgets'], 'page_object'));
        $t->same([2], array_column($page['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($page['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 560.0, 320.0, 584.0], $page['widgets'][0]['rect']);

        foreach ([
            'stale.parent.first',
            'Stale first Parent label',
            'Stale first Parent value must not surface',
            'duplicate.parent.stale-last',
            'Duplicate Parent stale label',
            'Stale duplicate Parent value must not surface',
            'duplicate.page.stale-last',
            'Duplicate page stale label',
            'Stale duplicate page value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm duplicate parent page boundary one'));
        $t->true(str_contains($visibleText, 'Visible AcroForm duplicate parent page boundary two'));
        $t->true(!str_contains($visibleText, 'Current duplicate Parent value'));
        $t->true(!str_contains($visibleText, 'Current duplicate page value'));
        $t->same(false, $parent['field_name_review']['alternate_name_used_as_visible_text']);
        $t->same(false, $page['field_hierarchy']['executes_form_actions']);
        $t->same(false, $page['field_hierarchy']['executes_javascript']);
    },
];
