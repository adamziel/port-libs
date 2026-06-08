<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainInheritedTrailerXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-08T10:48:07Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainInheritedTrailerPdf = static function () use ($xrefPrevChainInheritedTrailerXmp): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale inherited trailer page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited trailer page) Tj T* (Prev trailer graph repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-inherited-trailer"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-inherited-trailer"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainInheritedTrailerXmp(
        'Stale Inherited Trailer XMP Title',
        'Previous trailer graph must not select stale rows'
    ));
    $currentXmp = gzcompress($xrefPrevChainInheritedTrailerXmp(
        'Current Inherited Trailer XMP Title',
        'Inherited trailer graph selected current update objects'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress inherited-trailer xref Prev fixture streams.');
    }

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
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Inherited Trailer Info Title) /Author (Stale Inherited Author) /Producer (Stale Inherited Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-inherited-trailer.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-inherited-trailer.xml) /Desc (Stale inherited trailer attachment) /AFRelationship /Source /UF (stale-inherited-trailer.xml) /EF << /F 11 0 R >> >>');
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Inherited Trailer Info Title) /Author (Current Inherited Author) /Producer (Current Inherited Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-inherited-trailer.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-inherited-trailer.xml) /Desc (Current inherited trailer attachment) /AFRelationship /Source /UF (current-inherited-trailer.xml) /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = $xrefStreamRow(1, $fontOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress sparse inherited-trailer xref stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Prev ' . $previousXrefOffset . ' /Index [5 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [
        'pdf' => $pdf,
        'currentPayload' => $currentPayload,
        'previousXrefOffset' => $previousXrefOffset,
        'currentXrefOffset' => $currentXrefOffset,
    ];
};

return [
    'repairs current update objects reachable only through inherited Prev trailer references' => static function (
        TestRunner $t
    ) use ($xrefPrevChainInheritedTrailerPdf): void {
        $fixture = $xrefPrevChainInheritedTrailerPdf();
        $pdf = $fixture['pdf'];
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $extractor->extractPlainText($pdf);
        $latestTrailer = substr($pdf, (int) strrpos($pdf, '/Type /XRef'));
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(['Current inherited trailer page', 'Prev trailer graph repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current inherited trailer page\nPrev trailer graph repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Inherited Trailer XMP Title', $metadata['title']);
        $t->same('Inherited trailer graph selected current update objects', $metadata['description']);
        $t->same('Current Inherited Trailer Info Title', $metadata['info']['Title']);
        $t->same(['Current Inherited Author'], $metadata['authors']);
        $t->same('Current Inherited Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-08T10:48:07Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-inherited-trailer.xml', $files[0]['filename']);
        $t->same('Current inherited trailer attachment', $files[0]['description']);
        $t->same($fixture['currentPayload'], $files[0]['content']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-inherited-trailer.xml'], $summary['filenames']);
        $t->same(strlen($fixture['currentPayload']), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true($fixture['previousXrefOffset'] < $fixture['currentXrefOffset']);
        $t->true(str_contains($latestTrailer, '/Prev '));
        $t->true(str_contains($latestTrailer, '/Index [5 1]'));
        $t->true(!str_contains($latestTrailer, '/Root '));
        $t->true(!str_contains($latestTrailer, '/Info '));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Inherited'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-inherited-trailer'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-inherited-trailer'));
        $t->true(!str_contains($text, 'Stale inherited trailer page'));
        $t->true(!str_contains($text, "\0"));
    },
];
