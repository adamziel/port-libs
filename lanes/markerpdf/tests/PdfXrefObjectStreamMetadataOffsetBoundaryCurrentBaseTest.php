<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamMetadataOffsetBoundaryCurrentBasePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current metadata offset-boundary page) Tj ET';
    $fakeCatalog = '<< /Type /Catalog /Pages 2 0 R /Lang (zz-ZZ) >>';
    $carrierBody = '<< /Type /Catalog /Pages 2 0 R /Note (' . $fakeCatalog . ') >>';
    $objectData = $carrierBody . "\n";
    $badOffset = strpos($objectData, $fakeCatalog);
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate fake catalog inside object-stream literal string.');
    }

    $header = '12 0 1 ' . $badOffset;
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress metadata object-stream offset fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = '';
    for ($objectNumber = 0; $objectNumber < 9; $objectNumber++) {
        if ($objectNumber === 0) {
            $xrefRows .= $xrefRow(0, 0);
            continue;
        }

        if ($objectNumber === 1) {
            $xrefRows .= $xrefRow(2, 6, 1);
            continue;
        }

        if ($objectNumber === 6) {
            $xrefRows .= $xrefRow(1, $offsets[6]);
            continue;
        }

        if (isset($offsets[$objectNumber])) {
            $xrefRows .= $xrefRow(1, $offsets[$objectNumber]);
            continue;
        }

        $xrefRows .= $xrefRow(0, 0);
    }

    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress metadata object-stream xref fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "8 0 obj\n"
        . '<< /Type /XRef /Size 9 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects metadata object-stream member offsets inside literal strings before catalog review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamMetadataOffsetBoundaryCurrentBasePdf): void {
        $pdf = $xrefObjectStreamMetadataOffsetBoundaryCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $text = $textExtractor->extractPlainText($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $catalogEntry = $entries[1] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current metadata offset-boundary page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current metadata offset-boundary page', $text);
        $t->same(['1'], $textExtractor->extractPageLabels($pdf));
        $t->true(is_string($encodedMetadata));
        $t->true(!isset($metadata['language']));
        $t->true(!isset($metadata['catalog']['language']));
        $t->true(!str_contains((string) $encodedMetadata, 'zz-ZZ'));
        $t->true(!str_contains((string) $encodedMetadata, 'fake catalog'));
        $t->true(!str_contains($text, 'zz-ZZ'));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count']);
        $t->same(1, $catalogEntry['xref_member_index'] ?? null);
        $t->same(false, $catalogEntry['member_offset_token_boundary'] ?? null);
        $t->same(true, $catalogEntry['invalid_member_offset_rejected'] ?? null);
        $t->same('invalid_object_stream_member_offset', $catalogEntry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
