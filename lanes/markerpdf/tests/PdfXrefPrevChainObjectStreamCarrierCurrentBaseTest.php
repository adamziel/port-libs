<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainCarrierRecoveryXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-06T12:39:15Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainObjectStreamCarrierCurrentBasePdf = static function () use ($xrefPrevChainCarrierRecoveryXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale carrier Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current carrier Prev page) Tj T* (Recovered object stream carrier) Tj ET';
    $stalePayload = '<wp-export><post id="stale-carrier-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-carrier-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainCarrierRecoveryXmp(
        'Stale Carrier Prev XMP Title',
        'Stale direct metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainCarrierRecoveryXmp(
        'Current Carrier Prev XMP Title',
        'Current compressed catalog survives damaged carrier row'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref Prev object-stream carrier fixture streams.');
    }

    $objectStream = static function (array $members): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $compressed = gzcompress($header . "\n" . $objectData);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress xref Prev object stream carrier fixture.');
        }

        return [
            'first' => strlen($header) + 1,
            'indexes' => $memberIndexes,
            'content' => $compressed,
            'count' => count($members),
        ];
    };

    $carrier = $objectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        6 => '<< /Title (Current Carrier Prev Info Title) /Author (Current Carrier Author) /Producer (Current Carrier Producer) >>',
        8 => '<< /Names [(current-carrier-prev.xml) 10 0 R] >>',
        10 => '<< /Type /Filespec /F (current-carrier-prev.xml) /Desc (Current carrier Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
    ]);

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
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
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Carrier Prev Info Title) /Author (Stale Carrier Author) /Producer (Stale Carrier Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-carrier-prev.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-carrier-prev.xml) /Desc (Stale carrier Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $staleXrefOffset = strlen($pdf);
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
        . "startxref\n{$staleXrefOffset}\n%%EOF\n";

    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $carrierOffset = $addObject(30, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(2, 30, $carrier['indexes'][1])
        . $xrefStreamRow(2, 30, $carrier['indexes'][2])
        . $xrefStreamRow(2, 30, $carrier['indexes'][3])
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(2, 30, $carrier['indexes'][6])
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(2, 30, $carrier['indexes'][8])
        . $xrefStreamRow(2, 30, $carrier['indexes'][10])
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0)
        . $xrefStreamRow(1, $carrierOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress current xref Prev object-stream carrier rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Info 6 0 R /Prev ' . $staleXrefOffset . ' /Index [1 8 10 2 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $finalRows = $xrefStreamRow(1, 0, 0);
    $compressedFinalRows = gzcompress($finalRows);
    if (!is_string($compressedFinalRows)) {
        throw new RuntimeException('Unable to compress final damaged-carrier xref rows.');
    }

    $finalXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Info 6 0 R /Prev ' . $currentXrefOffset . ' /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedFinalRows) . " >>\n"
        . "stream\n{$compressedFinalRows}\nendstream\nendobj\n"
        . "startxref\n{$finalXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'recovers previous object-stream carrier when latest xref Prev section has a damaged carrier row' => static function (
        TestRunner $t
    ) use ($xrefPrevChainObjectStreamCarrierCurrentBasePdf): void {
        $pdf = $xrefPrevChainObjectStreamCarrierCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $currentPayload = '<wp-export><post id="current-carrier-prev"/></wp-export>';

        $t->same(['Current carrier Prev page', 'Recovered object stream carrier'], $extractor->extractTextLines($pdf));
        $t->same("Current carrier Prev page\nRecovered object stream carrier", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Carrier Prev XMP Title', $metadata['title']);
        $t->same('Current compressed catalog survives damaged carrier row', $metadata['description']);
        $t->same('Current Carrier Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Carrier Author'], $metadata['authors']);
        $t->same('Current Carrier Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-carrier-prev.xml', $files[0]['filename']);
        $t->same('Current carrier Prev attachment', $files[0]['description']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same(hash('sha256', $currentPayload), $files[0]['content_sha256']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-carrier-prev.xml'], $summary['filenames']);
        $t->same('current-carrier-prev.xml', $summary['attachments'][0]['filename']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Index [30 1]'));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Carrier Prev'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-carrier-prev'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-carrier-prev'));
        $t->true(!str_contains($text, 'Stale carrier Prev page'));
        $t->true(!str_contains($text, "\0"));
    },
];
