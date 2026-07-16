<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridLinearizedObjectStreamGenerationCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Linearized stale generation zero page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current linearized hybrid page) Tj T* (Hinted compressed generation skipped) Tj ET';

    $members = [
        2 => '<< /Type /Pages /Kids [4 1 R] /Count 1 >>',
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 8 0 R /Note (hinted stale generation zero member) >>',
        8 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        9 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    $memberOffsets = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $memberOffsets[$objectNumber] = strlen($objectData);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $first = strlen($header) + 1;
    $objectStreamPlain = $header . "\n" . $objectData;

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [ HHHHHHHHHA HHHHHHHHHB ] /O 4 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 1, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . $first . ' /Length ' . strlen($objectStreamPlain) . " >>\nstream\n{$objectStreamPlain}\nendstream");

    $hybridRows = ''
        . $xrefStreamRow(2, 6, $memberIndexes[2])
        . $xrefStreamRow(2, 6, $memberIndexes[4])
        . $xrefStreamRow(2, 6, $memberIndexes[8])
        . $xrefStreamRow(2, 6, $memberIndexes[9]);
    $compressedHybridRows = gzcompress($hybridRows);
    if (!is_string($compressedHybridRows)) {
        throw new RuntimeException('Unable to compress hybrid linearized xref-stream fixture.');
    }

    $hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 11 /Index [2 1 4 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridRows) . " >>\nstream\n{$compressedHybridRows}\nendstream");
    $addObject(10, 0, '<< /Type /Catalog /Pages 2 0 R >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 2\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . "3 1\n"
        . $xrefTableRow($offsets['3:0'])
        . "5 3\n"
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . "10 1\n"
        . $xrefTableRow($offsets['10:0'])
        . "trailer\n<< /Size 11 /Root 10 0 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    $objectStreamPayloadStart = strpos($pdf, "stream\n{$objectStreamPlain}", $offsets['6:0']);
    if ($objectStreamPayloadStart === false) {
        throw new RuntimeException('Unable to locate object-stream payload for hybrid linearized fixture.');
    }
    $hintObjectStart = $objectStreamPayloadStart + strlen("stream\n") + $first + $memberOffsets[4];

    return strtr($pdf, [
        'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
        'HHHHHHHHHA' => sprintf('%010d', $hintObjectStart),
        'HHHHHHHHHB' => sprintf('%010d', strlen($members[4])),
        'EEEEEEEEEE' => sprintf('%010d', $xrefOffset),
        'TTTTTTTTTT' => sprintf('%010d', $xrefOffset),
    ]);
};

return [
    'preserves repaired direct generation page when a linearized hint range covers the stale compressed member' => static function (
        TestRunner $t
    ) use ($xrefHybridLinearizedObjectStreamGenerationCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridLinearizedObjectStreamGenerationCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Current linearized hybrid page', 'Hinted compressed generation skipped'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current linearized hybrid page\nHinted compressed generation skipped", $text);
        $t->same("Current linearized hybrid page\nHinted compressed generation skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Linearized stale generation zero page'));
        $t->true(!str_contains($text, 'hinted stale generation zero member'));
        $t->true(!str_contains($text, "\0"));
    },
];
