<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainDuplicatePrevXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-07T15:41:21Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainDuplicatePrevPdf = static function () use ($xrefPrevChainDuplicatePrevXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale duplicate Prev base page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate Prev page) Tj T* (Last Prev wins) Tj ET';
    $stalePayload = '<wp-export><post id="stale-duplicate-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-duplicate-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainDuplicatePrevXmp(
        'Stale Duplicate Prev XMP Title',
        'First duplicate Prev target must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainDuplicatePrevXmp(
        'Current Duplicate Prev XMP Title',
        'Last duplicate Prev target selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress duplicate-Prev xref fixture streams.');
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
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Duplicate Prev Info Title) /Author (Stale Duplicate Author) /Producer (Stale Duplicate Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-duplicate-prev.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-duplicate-prev.xml) /Desc (Stale duplicate Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $baseXrefOffset = strlen($pdf);
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
        . "startxref\n{$baseXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Duplicate Prev Info Title) /Author (Current Duplicate Author) /Producer (Current Duplicate Producer) >>');
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-duplicate-prev.xml) 10 0 R] >>');
    $currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-duplicate-prev.xml) /Desc (Current duplicate Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $middleRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentNameTreeOffset, 0)
        . $xrefStreamRow(1, $currentFileSpecOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
    $compressedMiddleRows = gzcompress($middleRows);
    if (!is_string($compressedMiddleRows)) {
        throw new RuntimeException('Unable to compress middle duplicate-Prev xref-stream rows.');
    }

    $middleXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $baseXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMiddleRows) . " >>\n"
        . "stream\n{$compressedMiddleRows}\nendstream\nendobj\n"
        . "startxref\n{$middleXrefOffset}\n%%EOF\n";

    $latestRows = $xrefStreamRow(1, 0, 0);
    $compressedLatestRows = gzcompress($latestRows);
    if (!is_string($compressedLatestRows)) {
        throw new RuntimeException('Unable to compress latest duplicate-Prev xref-stream rows.');
    }

    $latestXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Prev ' . $baseXrefOffset . ' /Prev ' . $middleXrefOffset . ' /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
        . "stream\n{$compressedLatestRows}\nendstream\nendobj\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses the last top-level xref-stream Prev entry before inheriting incremental update rows' => static function (
        TestRunner $t
    ) use ($xrefPrevChainDuplicatePrevPdf): void {
        $pdf = $xrefPrevChainDuplicatePrevPdf();
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $currentPayload = '<wp-export><post id="current-duplicate-prev"/></wp-export>';

        $t->same(['Current duplicate Prev page', 'Last Prev wins'], $extractor->extractTextLines($pdf));
        $t->same("Current duplicate Prev page\nLast Prev wins", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Duplicate Prev XMP Title', $metadata['title']);
        $t->same('Last duplicate Prev target selected', $metadata['description']);
        $t->same('Current Duplicate Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Duplicate Author'], $metadata['authors']);
        $t->same('Current Duplicate Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-07T15:41:21Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-duplicate-prev.xml', $files[0]['filename']);
        $t->same('Current duplicate Prev attachment', $files[0]['description']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-duplicate-prev.xml'], $summary['filenames']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(substr_count($pdf, '/Prev ') >= 3);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Duplicate Prev'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-duplicate-prev'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-duplicate-prev'));
        $t->true(!str_contains($text, 'Stale duplicate Prev base page'));
        $t->true(!str_contains($text, "\0"));
    },
];
