<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamGenerationPrevCurrentBasePdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous selected carrier page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current carrier generation page) Tj T* (Previous member generation skipped) Tj ET';
    $replacementLeak = 'BT /F1 12 Tf 72 720 Td (Replacement generation carrier leak) Tj ET';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $objectData = '';
        $headerPairs = [];
        $memberIndexes = [];
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $compressed = gzcompress($header . "\n" . $objectData);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress focused object-stream generation fixture.');
        }

        return [$header, $compressed];
    };

    $previousMemberIndexes = [];
    $replacementMemberIndexes = [];
    [$previousHeader, $previousCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $previousMemberIndexes);
    [$replacementHeader, $replacementCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (replacement generation member) >>',
    ], $replacementMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream");

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, $previousMemberIndexes[4])
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream generation fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(6, 1, '<< /Type /ObjStm /N 1 /First ' . (strlen($replacementHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($replacementCompressed) . " >>\nstream\n{$replacementCompressed}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($replacementLeak) . " >>\nstream\n{$replacementLeak}\nendstream");

    $currentRows = ''
        . $row(1, $offsets['1:1'], 1)
        . $row(1, $offsets['2:1'], 1)
        . $row(1, $offsets['6:1'], 1)
        . $row(1, $offsets['8:0'], 0)
        . $row(1, $offsets['9:0'], 0)
        . $row(1, $offsets['10:0'], 0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current xref-stream generation fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 6 1 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'skips Prev type-2 rows when the current section replaces the selected object-stream carrier generation' => static function (TestRunner $t) use ($xrefObjectStreamGenerationPrevCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamGenerationPrevCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current carrier generation page', 'Previous member generation skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current carrier generation page', 'Previous member generation skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current carrier generation page\nPrevious member generation skipped", $text);
        $t->same("Current carrier generation page\nPrevious member generation skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Previous selected carrier page'));
        $t->true(!str_contains($text, 'Replacement generation carrier leak'));
        $t->true(!str_contains($text, 'replacement generation member'));
        $t->true(!str_contains($text, "\0"));
    },
];
