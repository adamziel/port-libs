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

$tailedCatalogAcroFormReferencePdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible catalog AcroForm reference tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R 50 0 R /Lang (en-US) >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (catalog.ref.tail) /TU (Catalog ref tail label) /TM (catalog-ref-tail-export) /V (Catalog ref tail value must not surface) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances true >>\nendobj\n"
        . "52 0 obj\n<< /FT /Tx /T (catalog.ref.tail.decoy) /V (Trailing catalog reference operand value) >>\nendobj\n"
        . "%%EOF";
};

$commentOnlyCatalogAcroFormReferencePdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible catalog AcroForm reference comment body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R % comment-only catalog AcroForm reference tail\n/Lang (en-US) >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (catalog.ref.comment) /TU (Catalog ref comment label) /TM (catalog-ref-comment-export) /V (Catalog ref comment value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects catalog AcroForm indirect references with trailing operands before field review' => static function (
        TestRunner $t
    ) use ($tailedCatalogAcroFormReferencePdf): void {
        $pdf = $tailedCatalogAcroFormReferencePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(false, $form['need_appearances']);
        $t->same([], $form['fields']);
        $t->same([], $form['calculation_order']);
        $t->same([], $form['calculation_order_review']);

        foreach ([
            'catalog.ref.tail',
            'Catalog ref tail label',
            'catalog-ref-tail-export',
            'Catalog ref tail value must not surface',
            'catalog.ref.tail.decoy',
            'Trailing catalog reference operand value',
        ] as $leak) {
            $t->same(false, str_contains($encoded, $leak));
            $t->same(false, str_contains($visibleText, $leak));
        }

        $t->same('Visible catalog AcroForm reference tail body', $visibleText);
    },
    'keeps catalog AcroForm indirect references with comment-only tails usable' => static function (
        TestRunner $t
    ) use ($commentOnlyCatalogAcroFormReferencePdf, $fieldsByName): void {
        $pdf = $commentOnlyCatalogAcroFormReferencePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(true, $form['need_appearances']);
        $t->same(['catalog.ref.comment'], array_keys($fields));
        $t->same(1, count($form['fields']));

        $field = $fields['catalog.ref.comment'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Catalog ref comment label', $field['alternate_name']);
        $t->same('catalog-ref-comment-export', $field['mapping_name']);
        $t->same('Catalog ref comment value', $field['value']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->same('Visible catalog AcroForm reference comment body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Catalog ref comment value'));
        $t->same(false, str_contains($visibleText, 'Catalog ref comment label'));
    },
];
