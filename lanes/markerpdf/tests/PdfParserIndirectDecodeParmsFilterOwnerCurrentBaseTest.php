<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserIndirectDecodeParmsFilterOwnerPdf = static function (): string {
    $contentPrefix = "% Stored Flate bytes deliberately contain stream-owner decoys.\n"
        . "endstream\nendobj\n"
        . "20 0 obj\n/ASCIIHexDecode\nendobj\n"
        . "21 0 obj\n<< /Predictor /Twelve /Columns 1 >>\nendobj\n";
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Current Owner) Tj T* '
        . '(DecodeParms Current Helper) Tj T* '
        . '(Fake Stream Objects Excluded) Tj ET';
    $compressed = gzcompress($contentPrefix . $visibleContent, 0);
    if (!is_string($compressed) || !str_contains($compressed, "\nendstream\nendobj\n20 0 obj")) {
        throw new RuntimeException('Unable to build stored Flate owner-boundary fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $pageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $filterOffset = $addObject(20, 0, '/FlateDecode');
    $decodeParmsOffset = $addObject(21, 0, '<< /Predictor 1 >>');
    $contentOffset = $addObject(4, 0, "<< /Filter 20 0 R /DecodeParms 21 0 R >>\nstream\n{$compressed}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $addObject(20, 0, '/ASCIIHexDecode');
    $addObject(21, 0, '<< /Predictor /Twelve /Columns 1 >>');

    $selectedOffsets = [
        1 => $catalogOffset,
        2 => $pagesOffset,
        3 => $pageOffset,
        4 => $contentOffset,
        5 => $fontOffset,
        20 => $filterOffset,
        21 => $decodeParmsOffset,
    ];

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 22\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 21; $objectNumber++) {
        $pdf .= isset($selectedOffsets[$objectNumber])
            ? $xrefRow($selectedOffsets[$objectNumber])
            : $xrefRow(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 22 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps indirect Filter and DecodeParms helpers bound to the current stream owner' => static function (TestRunner $t) use ($parserIndirectDecodeParmsFilterOwnerPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserIndirectDecodeParmsFilterOwnerPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Indirect Filter Current Owner',
            'DecodeParms Current Helper',
            'Fake Stream Objects Excluded',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Indirect Filter Current Owner\nDecodeParms Current Helper\nFake Stream Objects Excluded", $text);
        $t->same("Indirect Filter Current Owner\nDecodeParms Current Helper\nFake Stream Objects Excluded\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, '20 0 obj'));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, "\0"));
    },
];
