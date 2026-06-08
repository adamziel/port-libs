<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerFormXObjectPdf = static function (): string {
    $formContent = "BT /F1 12 Tf 0 0 Td (Visible Form XObject Boundary Text) Tj ET";
    $content = "BT /F1 12 Tf 72 720 Td (Before Form XObject Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Form XObject Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "q\n"
        . "1 0 0 1 72 704 cm\n"
        . "/FormText Do\n"
        . "Q\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Form XObject Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /FormText 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'closes preview-only fallback before form xobject do followed by stray ei operator' => static function (
        TestRunner $t
    ) use ($inlineImageTokenizerFormXObjectPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerFormXObjectPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Form XObject Boundary',
            'Visible Form XObject Boundary Text',
            'After Form XObject Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Form XObject Boundary Text'));
        $t->true(str_contains($plainText, 'After Form XObject Boundary'));
        $t->true(!str_contains($plainText, 'Form XObject Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'JBIG2Decode'));
        $t->true(!str_contains($plainText, '/FormText Do'));
    },
];
