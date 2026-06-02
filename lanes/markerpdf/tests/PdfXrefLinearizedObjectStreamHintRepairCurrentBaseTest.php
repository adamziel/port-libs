<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefLinearizedObjectStreamHintRepairCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current object stream page) Tj T* (Linearized hint member skipped) Tj ET';
    $hintContent = 'BT /F1 12 Tf 72 720 Td (Linearized object stream hint stale leak) Tj ET';

    $members = [
        2 => '<< /Type /Catalog /Pages 3 0 R >>',
        3 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        4 => '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [7 0 R 9 0 R] >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        7 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        9 => "<< /Length " . strlen($hintContent) . " >>\nstream\n{$hintContent}\nendstream",
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

    $addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [ HHHHHHHHHA HHHHHHHHHB ] /O 4 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . $first . ' /Length ' . strlen($objectStreamPlain) . " >>\nstream\n{$objectStreamPlain}\nendstream");

    $xrefRows = ''
        . chr(1) . pack('N', $offsets['1:0']) . chr(0)
        . chr(2) . pack('N', 6) . chr($memberIndexes[2])
        . chr(2) . pack('N', 6) . chr($memberIndexes[3])
        . chr(2) . pack('N', 6) . chr($memberIndexes[4])
        . chr(2) . pack('N', 6) . chr($memberIndexes[5])
        . chr(1) . pack('N', $offsets['6:0']) . chr(0)
        . chr(2) . pack('N', 6) . chr($memberIndexes[7])
        . chr(2) . pack('N', 6) . chr($memberIndexes[9]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress linearized object-stream xref fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 2 0 R /Index [1 7 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    $objectStreamPayloadStart = strpos($pdf, "stream\n{$objectStreamPlain}", $offsets['6:0']);
    if ($objectStreamPayloadStart === false) {
        throw new RuntimeException('Unable to locate object-stream payload for hint range fixture.');
    }
    $hintObjectStart = $objectStreamPayloadStart + strlen("stream\n") + $first + $memberOffsets[9];

    return strtr($pdf, [
        'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
        'HHHHHHHHHA' => sprintf('%010d', $hintObjectStart),
        'HHHHHHHHHB' => sprintf('%010d', strlen($members[9])),
        'EEEEEEEEEE' => sprintf('%010d', $xrefOffset),
        'TTTTTTTTTT' => sprintf('%010d', $xrefOffset),
    ]);
};

return [
    'preserves linearized object-stream carrier while skipping hinted member before current-base text extraction' => static function (TestRunner $t) use ($xrefLinearizedObjectStreamHintRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefLinearizedObjectStreamHintRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current object stream page', 'Linearized hint member skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current object stream page', 'Linearized hint member skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current object stream page\nLinearized hint member skipped", $text);
        $t->same("Current object stream page\nLinearized hint member skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Linearized object stream hint stale leak'));
        $t->true(!str_contains($text, "\0"));
    },
];
