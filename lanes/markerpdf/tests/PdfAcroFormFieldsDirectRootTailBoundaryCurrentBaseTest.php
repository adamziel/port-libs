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

$tailedDirectAcroFormRootPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm root tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (direct.root.tail) /TU (Direct root tail label) /TM (direct-root-tail-export) /V (Direct root tail value must not surface) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances true >>\nendobj\n"
        . "52 0 obj\n<< /FT /Tx /T (trailing.direct.root.decoy) /V (Trailing direct root decoy value) >>\nendobj\n"
        . "%%EOF";
};

$validDirectAcroFormRootPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm root comment body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> % comment-only direct AcroForm tail\n/Lang (en-US) >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (direct.root.comment) /TU (Direct root comment label) /TM (direct-root-comment-export) /V (Direct root comment value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects direct catalog AcroForm dictionaries with trailing top-level operands before field review' => static function (
        TestRunner $t
    ) use ($tailedDirectAcroFormRootPdf): void {
        $pdf = $tailedDirectAcroFormRootPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(false, $form['need_appearances']);
        $t->same([], $form['fields']);
        $t->same([], $form['calculation_order']);
        $t->same([], $form['calculation_order_review']);

        foreach ([
            'direct.root.tail',
            'Direct root tail label',
            'direct-root-tail-export',
            'Direct root tail value must not surface',
            'trailing.direct.root.decoy',
            'Trailing direct root decoy value',
        ] as $leak) {
            $t->same(false, str_contains($encoded, $leak));
            $t->same(false, str_contains($visibleText, $leak));
        }

        $t->same('Visible direct AcroForm root tail body', $visibleText);
    },
    'keeps direct catalog AcroForm dictionaries with comment-only tails and following catalog keys usable' => static function (
        TestRunner $t
    ) use ($validDirectAcroFormRootPdf, $fieldsByName): void {
        $pdf = $validDirectAcroFormRootPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(true, $form['need_appearances']);
        $t->same(['direct.root.comment'], array_keys($fields));
        $t->same(1, count($form['fields']));

        $field = $fields['direct.root.comment'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Direct root comment label', $field['alternate_name']);
        $t->same('direct-root-comment-export', $field['mapping_name']);
        $t->same('Direct root comment value', $field['value']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->same('Visible direct AcroForm root comment body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Direct root comment value'));
        $t->same(false, str_contains($visibleText, 'Direct root comment label'));
    },
];
