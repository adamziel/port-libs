<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOmittedPrevCarrierXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T11:29:40Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefObjectStreamOmittedPrevCarrierObjectStream = static function (array $members): array {
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
        throw new RuntimeException('Unable to compress omitted carrier Prev object stream fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$xrefObjectStreamOmittedPrevCarrierPdf = static function () use (
    $xrefObjectStreamOmittedPrevCarrierXmp,
    $xrefObjectStreamOmittedPrevCarrierObjectStream
): array {
    $currentPayload = '<wp-export><post id="current-omitted-prev-carrier"/></wp-export>';
    $stalePayload = '<wp-export><post id="stale-omitted-prev-carrier"/></wp-export>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current omitted Prev carrier page) Tj T* (Current compressed catalog selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale omitted Prev carrier page) Tj ET';
    $currentXmp = gzcompress($xrefObjectStreamOmittedPrevCarrierXmp(
        'Current Omitted Prev Carrier XMP Title',
        'Current compressed catalog selected after stale carrier row'
    ));
    $staleXmp = gzcompress($xrefObjectStreamOmittedPrevCarrierXmp(
        'Stale Omitted Prev Carrier XMP Title',
        'Stale carrier row must not select metadata'
    ));
    if (!is_string($currentXmp) || !is_string($staleXmp)) {
        throw new RuntimeException('Unable to compress omitted carrier Prev XMP fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleMetadataOffset = $addObject(12, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleEmbeddedOffset = $addObject(13, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $staleCarrier = $xrefObjectStreamOmittedPrevCarrierObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 12 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
        6 => '<< /Title (Stale Omitted Prev Carrier Info Title) /Author (Stale Omitted Prev Carrier Author) /Producer (Stale Omitted Prev Carrier Producer) >>',
        8 => '<< /Names [(stale-omitted-prev-carrier.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (stale-omitted-prev-carrier.xml) /Desc (Stale omitted Prev carrier attachment) /AFRelationship /Source /EF << /F 13 0 R >> >>',
    ]);
    $staleCarrierOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N ' . $staleCarrier['count'] . ' /First ' . $staleCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($staleCarrier['content']) . " >>\nstream\n{$staleCarrier['content']}\nendstream"
    );

    $previousRows = [];
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        $previousRows[$objectNumber] = $xrefTableRow(0, $objectNumber === 0 ? 65535 : 0, 'f');
    }
    $previousRows[2] = $xrefTableRow($stalePagesOffset);
    $previousRows[3] = $xrefTableRow($stalePageOffset);
    $previousRows[4] = $xrefTableRow($staleContentOffset);
    $previousRows[5] = $xrefTableRow($fontOffset);
    $previousRows[12] = $xrefTableRow($staleMetadataOffset);
    $previousRows[13] = $xrefTableRow($staleEmbeddedOffset);
    $previousRows[20] = $xrefTableRow($staleCarrierOffset);

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 21\n"
        . implode('', $previousRows)
        . "trailer\n<< /Size 21 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentCarrier = $xrefObjectStreamOmittedPrevCarrierObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
        6 => '<< /Title (Current Omitted Prev Carrier Info Title) /Author (Current Omitted Prev Carrier Author) /Producer (Current Omitted Prev Carrier Producer) >>',
        8 => '<< /Names [(current-omitted-prev-carrier.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (current-omitted-prev-carrier.xml) /Desc (Current omitted Prev carrier attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);
    $addObject(
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
        . $xrefStreamRow(2, 20, $currentCarrier['indexes'][8])
        . $xrefStreamRow(0, 0, 0)
        . $xrefStreamRow(2, 20, $currentCarrier['indexes'][10])
        . $xrefStreamRow(1, $currentEmbeddedOffset, 0);
    $currentXrefRows = gzcompress($currentRows);
    if (!is_string($currentXrefRows)) {
        throw new RuntimeException('Unable to compress current omitted carrier Prev xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 11] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefRows) . " >>\n"
        . "stream\n{$currentXrefRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload];
};

return [
    'recovers current object-stream carrier omitted from xref stream over stale Prev carrier for metadata and attachments' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOmittedPrevCarrierPdf): void {
        [$pdf, $currentPayload, $stalePayload] = $xrefObjectStreamOmittedPrevCarrierPdf();

        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $textExtractor->extractPlainText($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);
        $attachmentJson = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);

        $t->same(['Current omitted Prev carrier page', 'Current compressed catalog selected'], $textExtractor->extractTextLines($pdf));
        $t->same("Current omitted Prev carrier page\nCurrent compressed catalog selected", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Omitted Prev Carrier XMP Title', $metadata['title']);
        $t->same('Current compressed catalog selected after stale carrier row', $metadata['description']);
        $t->same('Current Omitted Prev Carrier Info Title', $metadata['info']['Title']);
        $t->same(['Current Omitted Prev Carrier Author'], $metadata['authors']);
        $t->same('Current Omitted Prev Carrier Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-05T11:29:40Z', $metadata['created_at_utc']);

        $t->same(1, count($embeddedFiles));
        $t->same('current-omitted-prev-carrier.xml', $embeddedFiles[0]['filename']);
        $t->same('Current omitted Prev carrier attachment', $embeddedFiles[0]['description']);
        $t->same('Source', $embeddedFiles[0]['relationship']);
        $t->same($currentPayload, $embeddedFiles[0]['content']);
        $t->same(hash('sha256', $currentPayload), $embeddedFiles[0]['content_sha256']);

        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-omitted-prev-carrier.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same('Current omitted Prev carrier attachment', $attachmentSummary['attachments'][0]['description']);
        $t->same('Source', $attachmentSummary['attachments'][0]['relationship']);
        $t->same(strlen($currentPayload), $attachmentSummary['attachments'][0]['byte_length']);
        $t->same(10, $attachmentSummary['attachments'][0]['file_spec_object_id']);

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(4, $review['compressed_entry_count']);
        $t->same('xref_selected_object_stream_carrier', $entries[10]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[10]['selection_policy'] ?? null);
        $t->true(is_string($metadataJson) && !str_contains($metadataJson, 'Stale Omitted Prev Carrier'));
        $t->true(is_string($embeddedJson) && !str_contains($embeddedJson, 'stale-omitted-prev-carrier'));
        $t->true(is_string($attachmentJson) && !str_contains($attachmentJson, 'stale-omitted-prev-carrier'));
        $t->true(!str_contains($metadataJson ?: '', $stalePayload));
        $t->true(!str_contains($embeddedJson ?: '', $stalePayload));
        $t->true(!str_contains($attachmentJson ?: '', $stalePayload));
        $t->true(!str_contains($text, 'Stale omitted Prev carrier page'));
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
