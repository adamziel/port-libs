<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainObjectStreamMetadataXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T04:07:18Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainObjectStream = static function (array $members): array {
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $plain = $header . "\n" . $objectData;
    $compressed = gzcompress($plain);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress xref Prev object-stream metadata fixture.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'count' => count($members),
        'content' => $compressed,
    ];
};

$xrefPrevChainObjectStreamMetadataPdf = static function () use (
    $xrefPrevChainObjectStreamMetadataXmp,
    $xrefPrevChainObjectStream
): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale object-stream Prev metadata page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current object-stream Prev metadata page) Tj T* (Compressed dictionaries selected) Tj ET';
    $stalePayload = '<wp-export><post id="stale-object-stream-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-object-stream-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainObjectStreamMetadataXmp(
        'Stale Object Stream Prev XMP Title',
        'Stale direct metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainObjectStreamMetadataXmp(
        'Current Object Stream Prev XMP Title',
        'Current compressed catalog metadata selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref Prev object-stream XMP fixture streams.');
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

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Object Stream Prev Info Title) /Author (Stale Object Stream Author) /Producer (Stale Object Stream Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-object-stream-prev.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-object-stream-prev.xml) /Desc (Stale object-stream attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $objectStream = $xrefPrevChainObjectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
        6 => '<< /Title (Current Object Stream Prev Info Title) /Author (Current Object Stream Author) /Producer (Current Object Stream Producer) >>',
        8 => '<< /Names [(current-object-stream-prev.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (current-object-stream-prev.xml) /Desc (Current object-stream attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);
    $objectStreamOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N ' . $objectStream['count'] . ' /First ' . $objectStream['first'] . ' /Filter /FlateDecode /Length ' . strlen($objectStream['content']) . " >>\nstream\n{$objectStream['content']}\nendstream"
    );

    $currentRows = ''
        . $xrefStreamRow(2, 20, $objectStream['indexes'][1])
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(2, 20, $objectStream['indexes'][6])
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(2, 20, $objectStream['indexes'][8])
        . $xrefStreamRow(0, 0, 0)
        . $xrefStreamRow(2, 20, $objectStream['indexes'][10])
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0)
        . $xrefStreamRow(1, $objectStreamOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress xref Prev object-stream current xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 11 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'selects current object-stream catalog metadata and attachments across xref Prev chain' => static function (
        TestRunner $t
    ) use ($xrefPrevChainObjectStreamMetadataPdf): void {
        $pdf = $xrefPrevChainObjectStreamMetadataPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current object-stream Prev metadata page', 'Compressed dictionaries selected'], $extractor->extractTextLines($pdf));
        $t->same("Current object-stream Prev metadata page\nCompressed dictionaries selected", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Object Stream Prev XMP Title', $metadata['title']);
        $t->same('Current compressed catalog metadata selected', $metadata['description']);
        $t->same('Current Object Stream Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Object Stream Author'], $metadata['authors']);
        $t->same('Current Object Stream Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-05T04:07:18Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-object-stream-prev.xml', $files[0]['filename']);
        $t->same('Current object-stream attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(10, $files[0]['file_spec_object']);
        $t->same(11, $files[0]['embedded_file_object']);
        $t->same('<wp-export><post id="current-object-stream-prev"/></wp-export>', $files[0]['content']);
        $t->same(hash('sha256', '<wp-export><post id="current-object-stream-prev"/></wp-export>'), $files[0]['content_sha256']);
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Object Stream'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-object-stream-prev'));
        $t->true(!str_contains($text, 'Stale object-stream Prev metadata page'));
        $t->true(!str_contains($text, "\0"));
    },
];
