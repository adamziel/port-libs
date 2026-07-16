<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceOptionalContentWrapperPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Base resource wrapper text) Tj ET '
        . '/OC /HiddenWrapped BDC BT /F1 12 Tf 72 704 Td (Hidden wrapped layer text) Tj ET q /InheritedForm Do Q EMC '
        . '/OC /VisibleWrapped BDC BT /F1 12 Tf 72 688 Td (Visible wrapped layer text) Tj ET EMC '
        . 'q /InheritedForm Do Q';
    $formContent = '/OC /HiddenWrapped BDC BT /F1 12 Tf 12 24 Td (Hidden wrapped form text) Tj ET EMC '
        . '/OC /VisibleWrapped BDC BT /F1 12 Tf 12 12 Td (Visible wrapped form text) Tj ET EMC';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedForm 6 0 R >> /Properties << /VisibleWrapped 30 0 R /HiddenWrapped 31 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /OCG /Name (Visible Wrapped Layer) >>\nendobj\n"
        . "21 0 obj\n<< /Type /OCG /Name (Hidden Wrapped Layer) >>\nendobj\n"
        . "30 0 obj\n20 0 R\nendobj\n"
        . "31 0 obj\n21 0 R\nendobj\n"
        . "%%EOF";
};

return [
    'filters inherited optional-content Properties that wrap OCG references before WordPress text extraction' => static function (TestRunner $t) use ($pageResourceOptionalContentWrapperPdf): void {
        $pdf = $pageResourceOptionalContentWrapperPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Base resource wrapper text',
            'Visible wrapped layer text',
            'Visible wrapped form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, substr_count($plainText, 'Visible wrapped layer text'));
        $t->same(1, substr_count($plainText, 'Visible wrapped form text'));
        $t->same(false, str_contains($plainText, 'Hidden wrapped layer text'));
        $t->same(false, str_contains($plainText, 'Hidden wrapped form text'));
        $t->same(false, str_contains($plainText, 'Hidden Wrapped Layer'));
        $t->same(false, str_contains($plainText, 'VisibleWrapped'));
        $t->same(false, str_contains($plainText, 'HiddenWrapped'));
    },
];
