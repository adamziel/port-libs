<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$typedDictionaryBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible typed non-field AcroForm body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 20 0 R 30 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.safe) /V (Safe value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Widget /FT /Tx /T (article.inline) /V (Inline widget value) /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /T (filespec.decoy) /V (Filespec value must not surface) /Kids [22 0 R] /F (embedded-review.txt) >>\nendobj\n"
        . "22 0 obj\n<< /FT /Tx /T (filespec.child.decoy) /V (Filespec child value must not surface) >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /T (signature.value.decoy) /V (Signature value dictionary must not surface) /Name (Standalone Signer) /Reason (Standalone signature value dictionary) /ByteRange [0 10 20 10] /Contents <01020304> >>\nendobj\n"
        . "%%EOF";
};

return [
    'excludes typed non-field dictionaries from AcroForm field trees before WordPress review' => static function (TestRunner $t) use ($typedDictionaryBoundaryPdf, $fieldsByName): void {
        $pdf = $typedDictionaryBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.safe', 'article.inline'], array_keys($fields));

        $safe = $fields['article.safe'];
        $t->same('Tx', $safe['field_type']);
        $t->same('Safe value', $safe['value']);
        $t->same(6, $safe['object']);
        $t->same([8], array_column($safe['widgets'], 'object'));
        $t->same(0, $safe['widgets'][0]['page_index']);
        $t->true($safe['widgets'][0]['referenced_from_page_annots']);

        $inline = $fields['article.inline'];
        $t->same('Tx', $inline['field_type']);
        $t->same('Inline widget value', $inline['value']);
        $t->same(10, $inline['object']);
        $t->same([10], array_column($inline['widgets'], 'object'));
        $t->same(1, $inline['widgets'][0]['page_annotation_index']);
        $t->true($inline['widgets'][0]['referenced_from_page_annots']);

        foreach ([
            'filespec.decoy',
            'filespec.decoy.filespec.child.decoy',
            'filespec.child.decoy',
            'signature.value.decoy',
        ] as $blockedName) {
            $t->same(false, isset($fields[$blockedName]));
        }

        $t->same('Visible typed non-field AcroForm body', $plainText);
        foreach ([
            'Filespec value must not surface',
            'Filespec child value must not surface',
            'Signature value dictionary must not surface',
            'Standalone Signer',
        ] as $blockedText) {
            $t->same(false, str_contains($plainText, $blockedText));
            $t->same(false, is_string($encodedForm) && str_contains($encodedForm, $blockedText));
        }
    },
];
