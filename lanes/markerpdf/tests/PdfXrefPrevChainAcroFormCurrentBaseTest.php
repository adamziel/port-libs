<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainAcroFormPdf = static function (): string {
    $previousPageText = 'BT /F1 12 Tf 72 720 Td (Previous xref stream AcroForm page body) Tj ET';
    $currentPageText = 'BT /F1 12 Tf 72 720 Td (Current xref stream AcroForm page body) Tj ET';
    $decoyPageText = 'BT /F1 12 Tf 72 720 Td (Decoy higher generation AcroForm page body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );
    $xrefStreamRow = static fn (int $type, int $offset, int $generation): string => chr($type)
        . pack('N', $offset)
        . chr($generation);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>');
    $addObject(4, 0, "<< /Length " . strlen($previousPageText) . " >>\nstream\n{$previousPageText}\nendstream");
    $addObject(5, 0, '<< /Fields [6 0 R] /NeedAppearances false /DA (/Prev 9 Tf 1 0 0 rg) >>');
    $addObject(6, 0, '<< /FT /Tx /T (previous.prev.email) /TU (Previous Prev email label) /V (previous-prev@example.test) /Kids [8 0 R] >>');
    $addObject(8, 0, '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefTableRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $offsetKey = $objectNumber . ':0';
        $pdf .= isset($offsets[$offsetKey])
            ? $xrefTableRow($offsets[$offsetKey])
            : $xrefTableRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /AcroForm 5 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Contents 4 1 R /Annots [8 1 R] >>');
    $addObject(4, 1, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
    $addObject(5, 1, '<< /Fields [6 1 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>');
    $addObject(6, 1, '<< /FT /Tx /T (current.prev.email) /TU (Current Prev email label) /V (current-prev@example.test) /Kids [8 1 R] >>');
    $addObject(8, 1, '<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 300 664] /P 3 1 R /F 4 >>');

    $addObject(1, 2, '<< /Type /Catalog /Pages 2 2 R /AcroForm 5 2 R >>');
    $addObject(2, 2, '<< /Type /Pages /Kids [3 2 R] /Count 1 >>');
    $addObject(3, 2, '<< /Type /Page /Parent 2 2 R /Contents 4 2 R /Annots [8 2 R] >>');
    $addObject(4, 2, "<< /Length " . strlen($decoyPageText) . " >>\nstream\n{$decoyPageText}\nendstream");
    $addObject(5, 2, '<< /Fields [6 2 R] /NeedAppearances false /DA (/Decoy 9 Tf 1 0 0 rg) >>');
    $addObject(6, 2, '<< /FT /Tx /T (decoy.prev.email) /TU (Decoy Prev email label) /V (decoy-prev@example.test) /Kids [8 2 R] >>');
    $addObject(8, 2, '<< /Subtype /Widget /Parent 6 2 R /Rect [72 640 300 664] /P 3 2 R /F 4 >>');

    $rows = '';
    foreach ([1, 2, 3, 4, 5, 6, 8] as $objectNumber) {
        $rows .= $xrefStreamRow(1, $offsets[$objectNumber . ':1'], 1);
    }
    $compressedRows = gzcompress($rows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress AcroForm xref-stream fixture rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $previousXrefOffset
        . ' /Index [1 6 8 1] /W [1 4 1] /Filter /FlateDecode /Length '
        . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

return [
    'selects current AcroForm field objects from xref-stream Prev chain before higher-generation decoys' => static function (
        TestRunner $t
    ) use ($xrefPrevChainAcroFormPdf, $fieldsByName): void {
        $pdf = $xrefPrevChainAcroFormPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.prev.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);
        $t->same('Current xref stream AcroForm page body', $visibleText);

        $field = $fields['current.prev.email'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('current-prev@example.test', $field['value']);
        $t->same('Current Prev email label', $field['alternate_name']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same(false, $field['action_review']['executes_action']);
        $t->same(false, $field['action_review']['executes_javascript']);

        $t->true(str_contains($pdf, '/Type /XRef'));
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Root 1 1 R'));

        foreach ([
            'previous.prev.email',
            'Previous Prev email label',
            'previous-prev@example.test',
            'Previous xref stream AcroForm page body',
            'decoy.prev.email',
            'Decoy Prev email label',
            'decoy-prev@example.test',
            'Decoy higher generation AcroForm page body',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }
    },
];
