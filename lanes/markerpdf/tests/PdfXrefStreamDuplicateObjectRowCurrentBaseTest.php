<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamDuplicateObjectRowCurrentBasePdf = static function (): string {
    $staleCompressedContent = 'BT /F1 12 Tf 72 700 Td (Stale duplicate xref-stream page) Tj T* (Earlier type two row leaked) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-row guard page) Tj ET';
    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
        10 => '<< /Title (Stale duplicate metadata title) /Author (Earlier compressed Info row) >>',
    ];
    $memberIndexes = [];
    $headerPairs = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStreamPayload = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPayload);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress duplicate-row object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");
    $addObject(6, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(8, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets[1])
        . $xrefRow(1, $offsets[2])
        . $xrefRow(1, $offsets[3])
        . $xrefRow(2, 6, 0)
        . $xrefRow(0, 0, 0)
        . $xrefRow(1, $offsets[5])
        . $xrefRow(1, $offsets[6])
        . $xrefRow(1, $offsets[8])
        . $xrefRow(1, $offsets[9])
        . $xrefRow(2, 6, $memberIndexes[10])
        . $xrefRow(0, 0, 0);

    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress duplicate-row xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 10 0 R /Index [1 3 4 1 4 1 5 2 8 2 10 1 10 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses later duplicate xref-stream object rows before object-stream expansion' => static function (
        TestRunner $t
    ) use ($xrefStreamDuplicateObjectRowCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamDuplicateObjectRowCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['Current duplicate-row guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current duplicate-row guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current duplicate-row guard page', $text);
        $t->same("Current duplicate-row guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale duplicate xref-stream page'));
        $t->true(!str_contains($text, 'Earlier type two row leaked'));
        $t->true(!str_contains($text, "\0"));
        $t->same(null, $metadata['title'] ?? null);
        $t->same(null, $metadata['author'] ?? null);
        $t->true(!str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'Stale duplicate metadata title'));
        $t->true(!str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'Earlier compressed Info row'));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(0, $review['unresolved_object_stream_carrier_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
