<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$acroFormTrailerRootBoundaryPdf = static function (): string {
    $stalePageText = 'BT /F1 12 Tf 72 720 Td (Stale AcroForm trailer root page body) Tj ET';
    $currentPageText = 'BT /F1 12 Tf 72 720 Td (Current AcroForm trailer root page body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>');
    $addObject(4, "<< /Length " . strlen($stalePageText) . " >>\nstream\n{$stalePageText}\nendstream");
    $addObject(5, '<< /Fields [6 0 R] /NeedAppearances false /DA (/Stale 9 Tf 1 0 0 rg) >>');
    $addObject(6, '<< /FT /Tx /T (stale.email) /TU (Stale email label) /V (stale@example.test) /Kids [8 0 R] >>');
    $addObject(8, '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>');

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /AcroForm 25 0 R >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Contents 23 0 R /Annots [28 0 R 32 0 R 35 0 R] >>');
    $addObject(23, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
    $addObject(25, '<< /Fields [26 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>');
    $addObject(26, '<< /FT /Tx /T (current.email) /TU (Current email label) /V (current@example.test) /Kids [28 0 R] >>');
    $addObject(28, '<< /Subtype /Widget /Parent 26 0 R /Rect [72 640 300 664] /P 22 0 R /F 4 >>');
    $addObject(30, '<< /FT /Ch /T (current.category) /V (page) /Opt [(post) (page)] /Kids [32 0 R] >>');
    $addObject(32, '<< /Subtype /Widget /Parent 30 0 R /Rect [72 600 260 624] /P 22 0 R /F 4 >>');
    $addObject(35, '<< /Subtype /Widget /FT /Tx /T (current.inline) /V (inline current value) /Rect [72 560 320 584] /P 22 0 R /F 4 >>');

    $xrefOffset = strlen($pdf);
    $maxObject = 35;
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
        . "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber])
            ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
            : "0000000000 00000 f \n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

return [
    'uses current trailer Root for AcroForm Fields and page widget boundaries' => static function (
        TestRunner $t
    ) use ($acroFormTrailerRootBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormTrailerRootBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.email', 'current.category', 'current.inline'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['current.email'];
        $t->same(26, $email['object']);
        $t->same('text', $email['field_type_label']);
        $t->same('current@example.test', $email['value']);
        $t->same('Current email label', $email['alternate_name']);
        $t->same([28], array_column($email['widgets'], 'object'));
        $t->same([22], array_column($email['widgets'], 'page_object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $email['value_state']['hierarchy_boundary']['current_value_source']);

        $category = $fields['current.category'];
        $t->same(30, $category['object']);
        $t->same('choice', $category['field_type_label']);
        $t->same('page', $category['value']);
        $t->same([['export' => 'post', 'label' => 'post'], ['export' => 'page', 'label' => 'page']], $category['options']);
        $t->same([32], array_column($category['widgets'], 'object'));
        $t->same([1], array_column($category['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($category['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $category['value_state']['hierarchy_boundary']['current_value_source']);

        $inline = $fields['current.inline'];
        $t->same(35, $inline['object']);
        $t->same('text', $inline['field_type_label']);
        $t->same('inline current value', $inline['value']);
        $t->same([35], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.email'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale@example.test'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale email label'));
        $t->true(str_contains($visibleText, 'Current AcroForm trailer root page body'));
        $t->true(!str_contains($visibleText, 'Stale AcroForm trailer root page body'));
        $t->true(!str_contains($visibleText, 'current@example.test'));
        $t->true(!str_contains($visibleText, 'inline current value'));
        $t->true(!str_contains($visibleText, 'stale@example.test'));
    },
];
