<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOmittedMetadataGraphXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-07T21:26:38Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefObjectStreamOmittedMetadataGraphObjectStream = static function (array $members): array {
    $headerPairs = [];
    $indexes = [];
    $body = '';
    foreach ($members as $objectNumber => $memberBody) {
        $headerPairs[] = $objectNumber . ' ' . strlen($body);
        $indexes[$objectNumber] = count($indexes);
        $body .= $memberBody . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $body);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress omitted metadata graph object stream fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$xrefObjectStreamOmittedMetadataGraphPdf = static function () use (
    $xrefObjectStreamOmittedMetadataGraphXmp,
    $xrefObjectStreamOmittedMetadataGraphObjectStream
): array {
    $stalePayload = '<wp-export><post id="stale-omitted-metadata-graph"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-omitted-metadata-graph"/></wp-export>';
    $staleText = 'BT /F1 12 Tf 72 720 Td (Stale omitted metadata graph page) Tj ET';
    $currentText = 'BT /F1 12 Tf 72 720 Td (Current omitted metadata graph page) Tj T* (Current compressed name tree repaired) Tj ET';
    $staleXmp = gzcompress($xrefObjectStreamOmittedMetadataGraphXmp(
        'Stale Omitted Metadata Graph XMP Title',
        'Stale compressed attachment graph must not win'
    ));
    $currentXmp = gzcompress($xrefObjectStreamOmittedMetadataGraphXmp(
        'Current Omitted Metadata Graph XMP Title',
        'Current compressed catalog attachment graph selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress omitted metadata graph XMP fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 12 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Omitted Metadata Graph Info Title) /Author (Stale Omitted Metadata Graph Author) /Producer (Stale Omitted Metadata Graph Producer) >>');
    $staleMetadataOffset = $addObject(12, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-omitted-metadata-graph.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-omitted-metadata-graph.xml) /Desc (Stale omitted metadata graph attachment) /AFRelationship /Source /EF << /F 13 0 R >> >>');
    $staleEmbeddedOffset = $addObject(13, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 14\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleEmbeddedOffset)
        . "trailer\n<< /Size 14 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentCarrier = $xrefObjectStreamOmittedMetadataGraphObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
        6 => '<< /Title (Current Omitted Metadata Graph Info Title) /Author (Current Omitted Metadata Graph Author) /Producer (Current Omitted Metadata Graph Producer) >>',
        8 => '<< /Names [(current-omitted-metadata-graph.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (current-omitted-metadata-graph.xml) /Desc (Current omitted metadata graph attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);
    $carrierOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N ' . $currentCarrier['count'] . ' /First ' . $currentCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($currentCarrier['content']) . " >>\nstream\n{$currentCarrier['content']}\nendstream"
    );

    $currentRows = ''
        . $xrefStreamRow(2, 20, $currentCarrier['indexes'][1])
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(2, 20, $currentCarrier['indexes'][6])
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedOffset, 0)
        . $xrefStreamRow(1, $carrierOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress omitted metadata graph xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 7 11 1 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload];
};

return [
    'repairs omitted compressed metadata name-tree graph members before stale Prev attachments' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOmittedMetadataGraphPdf): void {
        [$pdf, $currentPayload, $stalePayload] = $xrefObjectStreamOmittedMetadataGraphPdf();

        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $textExtractor->extractPlainText($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(['Current omitted metadata graph page', 'Current compressed name tree repaired'], $textExtractor->extractTextLines($pdf));
        $t->same("Current omitted metadata graph page\nCurrent compressed name tree repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Omitted Metadata Graph XMP Title', $metadata['title']);
        $t->same('Current compressed catalog attachment graph selected', $metadata['description']);
        $t->same('Current Omitted Metadata Graph Info Title', $metadata['info']['Title']);
        $t->same(['Current Omitted Metadata Graph Author'], $metadata['authors']);
        $t->same('Current Omitted Metadata Graph Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-07T21:26:38Z', $metadata['created_at_utc']);

        $t->same(1, count($embeddedFiles));
        $t->same('current-omitted-metadata-graph.xml', $embeddedFiles[0]['filename']);
        $t->same('Current omitted metadata graph attachment', $embeddedFiles[0]['description']);
        $t->same('Source', $embeddedFiles[0]['relationship']);
        $t->same('text/xml', $embeddedFiles[0]['mime_type']);
        $t->same(10, $embeddedFiles[0]['file_spec_object']);
        $t->same(11, $embeddedFiles[0]['embedded_file_object']);
        $t->same($currentPayload, $embeddedFiles[0]['content']);
        $t->same(hash('sha256', $currentPayload), $embeddedFiles[0]['content_sha256']);

        $t->same(1, $summary['attachment_count']);
        $t->same(['current-omitted-metadata-graph.xml'], $summary['filenames']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same('Current omitted metadata graph attachment', $summary['attachments'][0]['description']);
        $t->same('Source', $summary['attachments'][0]['relationship']);
        $t->same('original_source', $summary['attachments'][0]['relationship_role']);
        $t->same(10, $summary['attachments'][0]['file_spec_object_id']);
        $t->same(11, $summary['attachments'][0]['stream_object_id']);
        $t->same(strlen($currentPayload), $summary['attachments'][0]['byte_length']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same('xref_selected_object_stream_carrier', $entries[1]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->true(is_string($metadataJson) && !str_contains($metadataJson, 'Stale Omitted Metadata Graph'));
        $t->true(is_string($embeddedJson) && !str_contains($embeddedJson, 'stale-omitted-metadata-graph'));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'stale-omitted-metadata-graph'));
        $t->true(!str_contains($metadataJson ?: '', $stalePayload));
        $t->true(!str_contains($embeddedJson ?: '', $stalePayload));
        $t->true(!str_contains($summaryJson ?: '', $stalePayload));
        $t->true(!str_contains($text, 'Stale omitted metadata graph page'));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
