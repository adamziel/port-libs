<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserInlineStreamOwnerCurrentBasePdf = static function (?int $declaredLength = null): string {
    $inlineOwnerPayload = 'BT /F1 12 Tf 72 660 Td (Inline Stream Owner Leak) Tj ET';
    $inlineImagePayload = "<< /Length " . strlen($inlineOwnerPayload) . " >>\n"
        . "stream\n{$inlineOwnerPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n";
    $content = "BT /F1 12 Tf 72 720 Td (Before Inline Owner Boundary) Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 ID\n{$inlineImagePayload}\nEI\n"
        . 'BT /F1 12 Tf 72 700 Td (After Inline Owner Boundary) Tj ET';
    $streamDictionary = $declaredLength === null ? '<< >>' : '<< /Length ' . $declaredLength . ' >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "5 0 obj\n{$streamDictionary}\nstream\n{$content}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps missing or stale stream owners outside raw inline image endstream decoys' => static function (TestRunner $t) use ($parserInlineStreamOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        foreach ([null, 24] as $declaredLength) {
            $pdf = $parserInlineStreamOwnerCurrentBasePdf($declaredLength);
            $text = $extractor->extractPlainText($pdf);

            $expected = ['Before Inline Owner Boundary', 'After Inline Owner Boundary'];
            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before Inline Owner Boundary\nAfter Inline Owner Boundary", $text);
            $t->same("Before Inline Owner Boundary\nAfter Inline Owner Boundary\n", $extractor->naiveGetText($pdf));
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->same(['1'], $extractor->extractPageLabels($pdf));
            $t->true(!str_contains($text, 'Inline Stream Owner Leak'));
            $t->true(!str_contains($text, '20 0 obj'));
            $t->true(!str_contains($text, 'endstream'));
            $t->true(!str_contains($text, "\0"));
        }
    },
];
