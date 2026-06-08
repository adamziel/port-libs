<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamTruncatedIndexMetadataAttachmentXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-08T17:41:26Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefStreamTruncatedIndexMetadataAttachmentObjectStream = static function (array $members): array {
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
        throw new RuntimeException('Unable to compress truncated-index metadata object stream.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'count' => count($members),
        'content' => $compressed,
    ];
};

$xrefStreamTruncatedIndexMetadataAttachmentPdf = static function () use (
    $xrefStreamTruncatedIndexMetadataAttachmentXmp,
    $xrefStreamTruncatedIndexMetadataAttachmentObjectStream
): string {
    $payload = '<wp-export><post id="truncated-index-leak"/></wp-export>';
    $xmp = gzcompress($xrefStreamTruncatedIndexMetadataAttachmentXmp(
        'Truncated Index Leak Title',
        'Partial xref-stream rows must not select WordPress metadata'
    ));
    if (!is_string($xmp)) {
        throw new RuntimeException('Unable to compress truncated-index metadata XMP fixture.');
    }

    $carrier = $xrefStreamTruncatedIndexMetadataAttachmentObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
        6 => '<< /Title (Truncated Index Info Title) /Author (Truncated Index Author) /Producer (Truncated Index Producer) >>',
        8 => '<< /Names [(truncated-index-source.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (truncated-index-source.xml) /Desc (Truncated index WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, 0, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream");
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($payload) . ' /CheckSum <' . md5($payload) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
    $addObject(20, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");

    $xrefRows = ''
        . $row(2, 20, $carrier['indexes'][1])
        . $row(1, $offsets['2:0'])
        . $row(0, 0, 0)
        . $row(0, 0, 0)
        . $row(0, 0, 0)
        . $row(2, 20, $carrier['indexes'][6])
        . $row(1, $offsets['7:0'])
        . $row(2, 20, $carrier['indexes'][8])
        . $row(0, 0, 0)
        . $row(2, 20, $carrier['indexes'][10]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress truncated-index metadata xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Index [1 11] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed before metadata and attachment extraction when explicit xref-stream Index rows are truncated' => static function (
        TestRunner $t
    ) use ($xrefStreamTruncatedIndexMetadataAttachmentPdf): void {
        $pdf = $xrefStreamTruncatedIndexMetadataAttachmentPdf();
        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $alignmentEntry = $review['malformed_xref_stream_row_alignment_entries'][0] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same([], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same([], $metadata['info']);
        $t->same([], $files);
        $t->same(0, $summary['attachment_count']);
        $t->same([], $summary['filenames']);
        $t->same([], $summary['attachments']);
        $t->same([], $textExtractor->extractTextLines($pdf));
        $t->same('', $textExtractor->extractPlainText($pdf));
        $t->same(0, $textExtractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Truncated Index'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'truncated-index-source.xml'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'truncated-index-source.xml'));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_xref_stream_row_alignment_count'] ?? null);
        $t->same('truncated_xref_stream_index_rows', $alignmentEntry['owner_policy'] ?? null);
        $t->same(10, $alignmentEntry['decoded_entry_count'] ?? null);
        $t->same(11, $alignmentEntry['expected_entry_count'] ?? null);
        $t->same(true, $alignmentEntry['rejected_before_row_decode'] ?? null);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(str_contains($pdf, '/Index [1 11]'));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
    },
];
