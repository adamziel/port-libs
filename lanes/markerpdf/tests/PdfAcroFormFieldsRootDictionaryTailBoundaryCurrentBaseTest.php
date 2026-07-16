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

$tailedAcroFormRootDictionaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root dictionary tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> 50 0 R\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (root.tail.email) /TU (Root tail field label) /TM (root-tail-export) /V (Root tail field value must not surface) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances true >>\nendobj\n"
        . "52 0 obj\n<< /FT /Tx /T (trailing.root.decoy) /V (Trailing root decoy value) >>\nendobj\n"
        . "%%EOF";
};

$commentOnlyAcroFormRootDictionaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root dictionary comment boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> % comment-only root dictionary tail\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (root.comment.valid) /TU (Comment-only root field label) /TM (root-comment-export) /V (Comment-only root field value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed indirect AcroForm root dictionaries before field review' => static function (
        TestRunner $t
    ) use ($tailedAcroFormRootDictionaryPdf): void {
        $pdf = $tailedAcroFormRootDictionaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(false, $form['need_appearances']);
        $t->same([], $form['fields']);
        $t->same([], $form['calculation_order']);
        $t->same([], $form['calculation_order_review']);

        foreach ([
            'root.tail.email',
            'Root tail field label',
            'root-tail-export',
            'Root tail field value must not surface',
            'trailing.root.decoy',
            'Trailing root decoy value',
        ] as $fieldLeak) {
            $t->same(false, str_contains($encoded, $fieldLeak));
            $t->same(false, str_contains($visibleText, $fieldLeak));
        }

        $t->same('Visible AcroForm root dictionary tail body', $visibleText);
    },
    'keeps comment-only indirect AcroForm root dictionary tails usable' => static function (
        TestRunner $t
    ) use ($commentOnlyAcroFormRootDictionaryPdf, $fieldsByName): void {
        $pdf = $commentOnlyAcroFormRootDictionaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(true, $form['need_appearances']);
        $t->same(['root.comment.valid'], array_keys($fields));
        $t->same(1, count($form['fields']));

        $field = $fields['root.comment.valid'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Comment-only root field label', $field['alternate_name']);
        $t->same('root-comment-export', $field['mapping_name']);
        $t->same('Comment-only root field value', $field['value']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        $t->same('Visible AcroForm root dictionary comment boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Comment-only root field value'));
        $t->same(false, str_contains($visibleText, 'Comment-only root field label'));
    },
];
