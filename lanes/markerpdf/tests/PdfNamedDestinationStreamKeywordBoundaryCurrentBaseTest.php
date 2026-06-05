<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationStreamKeywordBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Stream keyword boundary start page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Stream keyword boundary appendix page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(5, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
    $addObject(6, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
    $addObject(7, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $compressedMembers = [
        1 => '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R /Dests << /LegacyCompressed [4 0 R /FitV 96] >> >>',
        8 => '<< /Dests 9 0 R >>',
        9 => '<< /Names [(Stream Keyword Start) [3 0 R /FitH 700] (Stream Keyword Appendix) 10 0 R] >>',
        10 => '<< /D [4 0 R /XYZ 72 640 0] >>',
    ];
    $objectStreamData = '';
    $objectStreamHeaderPairs = [];
    $memberIndexes = [];
    foreach ($compressedMembers as $objectNumber => $body) {
        $objectStreamHeaderPairs[] = $objectNumber . ' ' . strlen($objectStreamData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectStreamData .= $body . "\n";
    }
    $objectStreamHeader = implode(' ', $objectStreamHeaderPairs) . ' ';
    $objectStreamBytes = $objectStreamHeader . $objectStreamData;
    $compressedObjectStream = gzcompress($objectStreamBytes);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress stream-keyword boundary object stream fixture.');
    }

    $addObject(
        20,
        '<< /Type /ObjStm /N ' . count($compressedMembers)
        . ' /First ' . strlen($objectStreamHeader)
        . ' /Note (fake stream marker before real payload) /Filter /FlateDecode /Length ' . strlen($compressedObjectStream)
        . " >>\nstream\n{$compressedObjectStream}\nendstream"
    );

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyStale [3 0 R /FitH 111] >> >>');
    $addObject(8, '<< /Dests 9 0 R >>');
    $addObject(9, '<< /Names [(Stale Stream Keyword Start) [4 0 R /FitH 222] (Stale Stream Keyword Appendix) 10 0 R] >>');
    $addObject(10, '<< /D [3 0 R /FitV 33] >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if (isset($memberIndexes[$objectNumber])) {
            $rows .= $xrefRow(2, 20, $memberIndexes[$objectNumber]);
            continue;
        }
        if ($objectNumber === 21) {
            $rows .= $xrefRow(1, $xrefOffset);
            continue;
        }
        if (isset($offsets[$objectNumber])) {
            $rows .= $xrefRow(1, $offsets[$objectNumber]);
            continue;
        }

        $rows .= $xrefRow(0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress stream-keyword boundary xref stream fixture.');
    }

    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses the real object-stream payload when dictionary strings contain stream keywords' => static function (
        TestRunner $t
    ) use ($namedDestinationStreamKeywordBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationStreamKeywordBoundaryCurrentBasePdf()
        );

        $t->same(['Stream Keyword Start', 'Stream Keyword Appendix', 'LegacyCompressed'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 96.0], $destinations[2]['coordinates']);
    },
    'keeps stream-keyword decoy dictionary text out of WordPress visible text and metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationStreamKeywordBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationStreamKeywordBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

        $t->contains('Stream keyword boundary start page', $plainText);
        $t->contains('Stream keyword boundary appendix page', $plainText);
        foreach (['fake stream marker before real payload', 'Stale Stream Keyword Start', 'Stale Stream Keyword Appendix', 'LegacyStale', '222', '33'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(!str_contains($plainText, 'Stream Keyword Start'));
        $t->true(!str_contains($plainText, 'LegacyCompressed'));
    },
];
