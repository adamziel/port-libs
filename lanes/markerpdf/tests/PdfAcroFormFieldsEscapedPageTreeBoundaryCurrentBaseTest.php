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

$escapedPageTreeAcroFormFieldsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm escaped page tree body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /T#79pe /Pages /K#69ds [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /T#79pe /P#61ge /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.escapedpage) /TU (Listed escaped page label) /TM (listed-escaped-page-export) /V (Listed escaped page value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (pageonly.escapedpage) /TU (Page-only escaped page label) /V (publish) /Opt [(draft) (publish)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx /T (detached.escapedpage.decoy) /TU (Detached escaped page label must not surface) /V (Detached escaped page value must not surface) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'maps escaped page tree names before AcroForm page widget boundary repair' => static function (
        TestRunner $t
    ) use ($escapedPageTreeAcroFormFieldsPdf, $fieldsByName): void {
        $pdf = $escapedPageTreeAcroFormFieldsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.escapedpage', 'pageonly.escapedpage'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.escapedpage'];
        $t->same(6, $listed['object']);
        $t->same('text', $listed['field_type_label']);
        $t->same('Listed escaped page label', $listed['alternate_name']);
        $t->same('listed-escaped-page-export', $listed['mapping_name']);
        $t->same('Listed escaped page value', $listed['value']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $listed['widgets'][0]['rect']);

        $pageOnly = $fields['pageonly.escapedpage'];
        $t->same(10, $pageOnly['object']);
        $t->same('choice', $pageOnly['field_type_label']);
        $t->same('Page-only escaped page label', $pageOnly['alternate_name']);
        $t->same('publish', $pageOnly['value']);
        $t->same([
            ['export' => 'draft', 'label' => 'draft'],
            ['export' => 'publish', 'label' => 'publish'],
        ], $pageOnly['options']);
        $t->same([12], array_column($pageOnly['widgets'], 'object'));
        $t->same([0], array_column($pageOnly['widgets'], 'page_index'));
        $t->same([3], array_column($pageOnly['widgets'], 'page_object'));
        $t->same([1], array_column($pageOnly['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($pageOnly['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'detached.escapedpage.decoy',
            'Detached escaped page label must not surface',
            'Detached escaped page value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm escaped page tree body', $visibleText);
        $t->true(!str_contains($visibleText, 'Listed escaped page value'));
        $t->true(!str_contains($visibleText, 'publish'));
    },
];
