<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamDictionaryOwnerCurrentBasePdf = static function (): string {
    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused stream-dictionary owner rows must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $rowOne = 'BT /F1 12 Tf 72 720 Td (Current stream dictionary owner) Tj T* ';
    $rowTwo = str_pad('(Current DecodeParms helper applied) Tj ET', strlen($rowOne));
    $currentPayload = $rowOne . $rowTwo;
    $currentCompressed = gzcompress($pngSubPredictorEncode($currentPayload, strlen($rowOne)), 0);
    $stalePayload = 'BT /F1 12 Tf 72 720 Td (Stale stream dictionary leak) Tj ET';
    $staleCompressed = gzcompress($stalePayload, 0);
    if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
        throw new RuntimeException('Unable to compress focused stream-dictionary owner fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
    $addObject(4, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '1');
    $addObject(8, 0, '/ASCIIHexDecode');
    $addObject(9, 0, '<< /Predictor /Twelve /Columns 1 >>');
    $addObject(4, 1, "<< /Filter 8 1 R /DecodeParms 9 1 R /Length 7 1 R /Note (99 0 obj fake dictionary owner) >>\nstream\n{$currentCompressed}\nendstream");
    $addObject(7, 1, (string) strlen($currentCompressed));
    $addObject(8, 1, '/FlateDecode');
    $addObject(9, 1, '<< /Predictor 12 /Columns ' . strlen($rowOne) . ' >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 10\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
        if (isset($offsets[$objectNumber . ':1'])) {
            $pdf .= $xrefRow($offsets[$objectNumber . ':1'], 1);
            continue;
        }

        $pdf .= isset($offsets[$objectNumber . ':0'])
            ? $xrefRow($offsets[$objectNumber . ':0'])
            : $xrefRow(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps current xref stream dictionary owner before stale same-number dictionaries' => static function (TestRunner $t) use ($parserStreamDictionaryOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamDictionaryOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Current stream dictionary owner', 'Current DecodeParms helper applied'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current stream dictionary owner\nCurrent DecodeParms helper applied", $text);
        $t->same("Current stream dictionary owner\nCurrent DecodeParms helper applied\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale stream dictionary leak'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, "\0"));
    },
];
