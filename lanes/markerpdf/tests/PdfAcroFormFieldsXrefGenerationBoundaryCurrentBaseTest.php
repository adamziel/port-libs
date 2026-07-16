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

$xrefSelectedGenerationAcroFormPdf = static function (): string {
    $currentPageText = 'BT /F1 12 Tf 72 720 Td (Current xref generation AcroForm page body) Tj ET';
    $stalePageText = 'BT /F1 12 Tf 72 720 Td (Stale higher generation AcroForm page body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        if ($generation === 0) {
            $offsets[$objectNumber] = strlen($pdf);
        }

        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(20, 0, '<< /Type /Catalog /Pages 21 0 R /AcroForm 25 0 R >>');
    $addObject(21, 0, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, 0, '<< /Type /Page /Parent 21 0 R /Contents 23 0 R /Annots [28 0 R 32 0 R] >>');
    $addObject(23, 0, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
    $addObject(25, 0, '<< /Fields [26 0 R 30 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>');
    $addObject(26, 0, '<< /FT /Tx /T (current.xref.email) /TU (Current xref email label) /V (current-xref@example.test) /Kids [28 0 R] >>');
    $addObject(28, 0, '<< /Subtype /Widget /Parent 26 0 R /Rect [72 640 300 664] /P 22 0 R /F 4 >>');
    $addObject(30, 0, '<< /FT /Ch /T (current.xref.status) /V (publish) /Opt [(draft) (publish)] /Kids [32 0 R] >>');
    $addObject(32, 0, '<< /Subtype /Widget /Parent 30 0 R /Rect [72 600 260 624] /P 22 0 R /F 4 >>');

    $addObject(20, 1, '<< /Type /Catalog /Pages 41 1 R /AcroForm 45 1 R >>');
    $addObject(41, 1, '<< /Type /Pages /Kids [42 1 R] /Count 1 >>');
    $addObject(42, 1, '<< /Type /Page /Parent 41 1 R /Contents 43 1 R /Annots [48 1 R] >>');
    $addObject(43, 1, "<< /Length " . strlen($stalePageText) . " >>\nstream\n{$stalePageText}\nendstream");
    $addObject(45, 1, '<< /Fields [46 1 R] /NeedAppearances false /DA (/Stale 9 Tf 1 0 0 rg) >>');
    $addObject(46, 1, '<< /FT /Tx /T (stale.xref.email) /TU (Stale xref email label) /V (stale-xref@example.test) /Kids [48 1 R] >>');
    $addObject(48, 1, '<< /Subtype /Widget /Parent 46 1 R /Rect [72 640 300 664] /P 42 1 R /F 4 >>');

    $xrefOffset = strlen($pdf);
    $maxObject = 48;
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
        . "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
        if (isset($offsets[$objectNumber])) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
            continue;
        }

        $pdf .= "0000000000 00000 f \n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses xref selected object generations before AcroForm field repair' => static function (
        TestRunner $t
    ) use ($xrefSelectedGenerationAcroFormPdf, $fieldsByName): void {
        $pdf = $xrefSelectedGenerationAcroFormPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.xref.email', 'current.xref.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['current.xref.email'];
        $t->same(26, $email['object']);
        $t->same('text', $email['field_type_label']);
        $t->same('current-xref@example.test', $email['value']);
        $t->same('Current xref email label', $email['alternate_name']);
        $t->same([28], array_column($email['widgets'], 'object'));
        $t->same([22], array_column($email['widgets'], 'page_object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $status = $fields['current.xref.status'];
        $t->same(30, $status['object']);
        $t->same('choice', $status['field_type_label']);
        $t->same('publish', $status['value']);
        $t->same([32], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));
        $t->same([
            ['export' => 'draft', 'label' => 'draft'],
            ['export' => 'publish', 'label' => 'publish'],
        ], $status['options']);

        foreach ([
            'stale.xref.email',
            'Stale xref email label',
            'stale-xref@example.test',
            'Stale higher generation AcroForm page body',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }

        $t->same('Current xref generation AcroForm page body', $visibleText);
        $t->true(!str_contains($visibleText, 'current-xref@example.test'));
        $t->true(!str_contains($visibleText, 'publish'));
    },
];
