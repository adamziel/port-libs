<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainForwardPrevXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-07T14:17:32Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainForwardPrevPdf = static function () use ($xrefPrevChainForwardPrevXmp): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale forward Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current forward Prev page) Tj T* (Repaired stale xref-stream rows) Tj ET';
    $staleAttachment = '<wp-export><post id="stale-forward-prev"/></wp-export>';
    $currentAttachment = '<wp-export><post id="current-forward-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainForwardPrevXmp(
        'Stale Forward Prev XMP Title',
        'Stale xref-stream rows should not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainForwardPrevXmp(
        'Current Forward Prev XMP Title',
        'Current rows survive forward Prev repair'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref forward-Prev metadata fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names 8 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Title (Stale Forward Prev Info Title) /Author (Stale Forward Author) /Producer (Stale Forward Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(8, 0, '<< /EmbeddedFiles << /Names [(stale-forward-prev.xml) 10 0 R] >> >>');
    $addObject(10, 0, '<< /Type /Filespec /F (stale-forward-prev.xml) /UF (stale-forward-prev.xml) /Desc (Stale forward Prev attachment) /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleAttachment) . " >>\nstream\n{$staleAttachment}\nendstream");

    $staleOffsets = $offsets;
    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['10:0'])
        . $xrefTableRow($offsets['11:0'])
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names 8 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Forward Prev Info Title) /Author (Current Forward Author) /Producer (Current Forward Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /EmbeddedFiles << /Names [(current-forward-prev.xml) 10 0 R] >> >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-forward-prev.xml) /UF (current-forward-prev.xml) /Desc (Current forward Prev attachment) /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentAttachment) . " >>\nstream\n{$currentAttachment}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $staleOffsets['1:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['2:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['3:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['4:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['5:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['6:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['7:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['8:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['10:0'], 0)
        . $xrefStreamRow(1, $staleOffsets['11:0'], 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress current xref-stream forward-Prev fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $forwardPrev = $currentXrefOffset + 7;
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $forwardPrev . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [
        'pdf' => $pdf,
        'currentAttachment' => $currentAttachment,
        'currentXrefOffset' => $currentXrefOffset,
        'forwardPrev' => $forwardPrev,
        'previousXrefOffset' => $previousXrefOffset,
    ];
};

return [
    'repairs stale current xref-stream rows after forward Prev fallback' => static function (
        TestRunner $t
    ) use ($xrefPrevChainForwardPrevPdf): void {
        $fixture = $xrefPrevChainForwardPrevPdf();
        $pdf = $fixture['pdf'];
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->true($fixture['forwardPrev'] > $fixture['currentXrefOffset']);
        $t->true($fixture['previousXrefOffset'] < $fixture['currentXrefOffset']);
        $t->same(['Current forward Prev page', 'Repaired stale xref-stream rows'], $extractor->extractTextLines($pdf));
        $t->same("Current forward Prev page\nRepaired stale xref-stream rows", $plainText);
        $t->same('Current Forward Prev XMP Title', $metadata['title']);
        $t->same('Current rows survive forward Prev repair', $metadata['description']);
        $t->same('Current Forward Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Forward Author'], $metadata['authors']);
        $t->same('Current Forward Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-07T14:17:32Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-forward-prev.xml', $files[0]['filename']);
        $t->same('Current forward Prev attachment', $files[0]['description']);
        $t->same($fixture['currentAttachment'], $files[0]['content']);
        $t->same(hash('sha256', $fixture['currentAttachment']), $files[0]['content_sha256']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-forward-prev.xml'], $summary['filenames']);
        $t->same(strlen($fixture['currentAttachment']), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Forward Prev'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-forward-prev'));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-forward-prev'));
        $t->true(!str_contains($plainText, 'Stale forward Prev page'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
