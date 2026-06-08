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

$acroFormWidgetRectOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget rect operand boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (valid.indirect.rect) /V (Valid indirect rect value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [30 0 R 31 0 R 32 0 R 33 0 R] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (malformed.extra.rect) /V (Malformed extra rect value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624 999] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (malformed.trailing.rect) /V (Malformed trailing rect value) /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] 99 0 R /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Tx /T (malformed.indirect.rect) /V (Malformed indirect rect value) /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect 40 0 R /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n320\nendobj\n"
        . "31 0 obj\n664\nendobj\n"
        . "32 0 obj\n72\nendobj\n"
        . "33 0 obj\n640\nendobj\n"
        . "40 0 obj\n[72 520 320 544 123]\nendobj\n"
        . "99 0 obj\n<< /Subtype /Widget /FT /Tx /T (stale.trailing.rect.decoy) /V (Stale trailing rect decoy value) /Rect [72 500 320 524] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects malformed AcroForm widget Rect operands while preserving exact indirect rectangles' => static function (
        TestRunner $t
    ) use ($acroFormWidgetRectOperandBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormWidgetRectOperandBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['valid.indirect.rect', 'malformed.extra.rect', 'malformed.trailing.rect', 'malformed.indirect.rect'], array_keys($fields));
        $t->same(4, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $valid = $fields['valid.indirect.rect'];
        $t->same([8], array_column($valid['widgets'], 'object'));
        $t->same([72.0, 640.0, 320.0, 664.0], $valid['widgets'][0]['rect']);
        $t->same([0], array_column($valid['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($valid['widgets'], 'referenced_from_page_annots'));

        foreach (['malformed.extra.rect', 'malformed.trailing.rect', 'malformed.indirect.rect'] as $name) {
            $field = $fields[$name];
            $t->same(1, count($field['widgets']));
            $t->same(null, $field['widgets'][0]['rect']);
            $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        }

        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.trailing.rect.decoy'));
        $t->same('Visible AcroForm widget rect operand boundary body', $visibleText);
        foreach (['Valid indirect rect value', 'Malformed extra rect value', 'Malformed trailing rect value', 'Malformed indirect rect value', 'Stale trailing rect decoy value'] as $reviewText) {
            $t->true(!str_contains($visibleText, $reviewText));
        }
    },
];
