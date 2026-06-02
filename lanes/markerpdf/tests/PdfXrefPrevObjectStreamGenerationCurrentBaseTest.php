<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevObjectStreamGenerationCurrentBasePdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous compressed generation page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current object-stream generation guard) Tj T* (Reused carrier generation ignored) Tj ET';
    $replacementLeak = 'BT /F1 12 Tf 72 720 Td (Replacement generation object stream leak) Tj ET';

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
    $carrierDecoyIndexes = [];
    $replacementMemberIndexes = [];
    [$previousHeader, $previousCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $previousMemberIndexes);
    [$carrierDecoyHeader, $carrierDecoyCompressed] = $objectStream([
        6 => '<< /Type /ObjStm /Note (compressed carrier generation decoy) >>',
    ], $carrierDecoyIndexes);
    [$replacementHeader, $replacementCompressed] = $objectStream([
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (replacement generation carrier member) >>',
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
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($carrierDecoyHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($carrierDecoyCompressed) . " >>\nstream\n{$carrierDecoyCompressed}\nendstream");

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, $previousMemberIndexes[4])
        . $row(1, $offsets['5:0'], 0)
        . $row(2, 7, $carrierDecoyIndexes[6])
        . $row(1, $offsets['7:0'], 0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
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
        . $row(1, $offsets['8:0'], 0)
        . $row(1, $offsets['9:0'], 0)
        . $row(1, $offsets['10:0'], 0);
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
    'skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy' => static function (TestRunner $t) use ($xrefPrevObjectStreamGenerationCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefPrevObjectStreamGenerationCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current object-stream generation guard', 'Reused carrier generation ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current object-stream generation guard', 'Reused carrier generation ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current object-stream generation guard\nReused carrier generation ignored", $text);
        $t->same("Current object-stream generation guard\nReused carrier generation ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Previous compressed generation page'));
        $t->true(!str_contains($text, 'Replacement generation object stream leak'));
        $t->true(!str_contains($text, 'replacement generation carrier member'));
        $t->true(!str_contains($text, 'compressed carrier generation decoy'));
        $t->true(!str_contains($text, "\0"));
    },
];
