<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTextObjectStreamBoundaryCurrentBasePdf = static function (string $content, string $streamDictionary): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n{$streamDictionary}\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps text-object BI tokens from truncating stale-Length stream repair before WordPress import' => static function (
        TestRunner $t
    ) use ($inlineImageTextObjectStreamBoundaryCurrentBasePdf): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before Text BI Endstream) Tj\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
            . "(Text Object BI With EI Survives) Tj\n"
            . "EI\n"
            . "endstream\n"
            . "(After Fake Endstream Token Survives) Tj ET\n"
            . "BT /F1 12 Tf 72 704 Td (After Text BI Endstream) Tj ET\n";
        $expected = [
            'Before Text BI EndstreamText Object BI With EI SurvivesAfter Fake Endstream Token Survives',
            'After Text BI Endstream',
        ];
        $expectedRuns = [
            'Before Text BI Endstream',
            'Text Object BI With EI Survives',
            'After Fake Endstream Token Survives',
            'After Text BI Endstream',
        ];
        $streamDictionaries = [
            'missing length' => '<< >>',
            'short stale length' => '<< /Length 50 >>',
            'overdeclared stale length' => '<< /Length 9999 >>',
            'declared exact length control' => '<< /Length ' . strlen($content) . ' >>',
        ];
        $extractor = new PdfTextExtractor();

        foreach ($streamDictionaries as $label => $streamDictionary) {
            $pdf = $inlineImageTextObjectStreamBoundaryCurrentBasePdf($content, $streamDictionary);
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf), $label . ' text lines');
            $t->same($expectedRuns, $extractor->extractTextRuns($pdf), $label . ' text runs');
            $t->same(implode("\n", $expected), $plainText, $label . ' plain text');
            $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf), $label . ' naive text');
            $t->true(!str_contains($plainText, 'endstream'), $label . ' excludes fake endstream operator text');
            $t->same(['1'], $extractor->extractPageLabels($pdf), $label . ' page labels');
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages'], $label . ' outline page count');
        }
    },
];
