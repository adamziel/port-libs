<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefIncrementalObjectStreamFreeRepairCurrentBasePdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous compressed carrier page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current incremental guard page) Tj T* (Unlisted carrier ignored) Tj ET';
    $replacementLeak = 'BT /F1 12 Tf 72 720 Td (Unlisted replacement object stream leak) Tj ET';

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
            throw new RuntimeException('Unable to compress focused object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $previousMemberIndexes = [];
    $replacementMemberIndexes = [];
    [$previousHeader, $previousCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $previousMemberIndexes);
    [$replacementHeader, $replacementCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (unlisted replacement carrier member) >>',
    ], $replacementMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream");

    $previousRows = ''
        . chr(1) . pack('N', $offsets['1:0']) . chr(0)
        . chr(1) . pack('N', $offsets['2:0']) . chr(0)
        . chr(1) . pack('N', $offsets['3:0']) . chr(0)
        . chr(2) . pack('N', 6) . chr($previousMemberIndexes[4])
        . chr(1) . pack('N', $offsets['5:0']) . chr(0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($replacementHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($replacementCompressed) . " >>\nstream\n{$replacementCompressed}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($replacementLeak) . " >>\nstream\n{$replacementLeak}\nendstream");

    $currentRows = ''
        . chr(1) . pack('N', $offsets['1:1']) . chr(1)
        . chr(1) . pack('N', $offsets['2:1']) . chr(1)
        . chr(1) . pack('N', $offsets['8:0']) . chr(0)
        . chr(1) . pack('N', $offsets['9:0']) . chr(0)
        . chr(1) . pack('N', $offsets['10:0']) . chr(0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'skips previous type-2 rows whose object-stream carrier was never selected before incremental free repair' => static function (TestRunner $t) use ($xrefIncrementalObjectStreamFreeRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefIncrementalObjectStreamFreeRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current incremental guard page', 'Unlisted carrier ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current incremental guard page', 'Unlisted carrier ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current incremental guard page\nUnlisted carrier ignored", $text);
        $t->same("Current incremental guard page\nUnlisted carrier ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Previous compressed carrier page'));
        $t->true(!str_contains($text, 'Unlisted replacement object stream leak'));
        $t->true(!str_contains($text, 'unlisted replacement carrier member'));
        $t->true(!str_contains($text, "\0"));
    },
];
