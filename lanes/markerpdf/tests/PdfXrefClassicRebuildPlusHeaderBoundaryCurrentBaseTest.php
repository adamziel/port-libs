<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicRebuildPlusHeaderCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $staleXmp = $xmpPacket('Stale Plus Header XRef Title');
    $currentXmp = $xmpPacket('Current Plus Header XRef Title');
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale plus-header xref page) Tj T* (Old plus-header trailer leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current plus-header xref page) Tj T* (Plus-signed subsection header repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-plus-header-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-plus-header-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale Plus Header XRef Info) /Author (Stale Plus Importer) >>');
    $addObject(8, '<< /Names [(stale-plus-header-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-plus-header-xref.xml) /Desc (Stale plus-header xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current Plus Header XRef Info) /Author (Current Plus Importer) >>');
    $addObject(28, '<< /Names [(current-plus-header-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-plus-header-xref.xml) /Desc (Current plus-header xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "+20 +12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $currentPayload, strtolower($currentChecksum)];
};

$xrefPlusHeaderFreeObjectMapPdf = static function (): string {
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R >>');
    $addObject(3, '<< /Type /Pages /Kids [] /Count 0 >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "+0 +4\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow(0, 7, 'f')
        . $xrefRow($offsets[3])
        . "trailer\n<< /Size 4 /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rebuilds damaged classic startxref with plus-signed subsection header before current import' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildPlusHeaderCurrentBasePdf, $xrefPlusHeaderFreeObjectMapPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $currentPayload, $currentChecksum] = $xrefClassicRebuildPlusHeaderCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $pageBoundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($xrefPlusHeaderFreeObjectMapPdf());
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current plus-header xref page', 'Plus-signed subsection header repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current plus-header xref page', 'Plus-signed subsection header repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current plus-header xref page\nPlus-signed subsection header repaired", $text);
        $t->same("Current plus-header xref page\nPlus-signed subsection header repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Plus Header XRef Title', $metadata['title']);
        $t->same('Current Plus Header XRef Info', $metadata['info']['Title']);
        $t->same('Current Plus Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('catalog_names_embedded_files', $files[0]['source']);
        $t->same('current-plus-header-xref.xml', $files[0]['name']);
        $t->same('current-plus-header-xref.xml', $files[0]['filename']);
        $t->same('Current plus-header xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(30, $files[0]['file_spec_object']);
        $t->same(31, $files[0]['embedded_file_object']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same(strlen($currentPayload), $files[0]['declared_size']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(['current-plus-header-xref.xml'], $attachmentSummary['filenames']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->same(1, count($pageBoundary));
        $t->same(22, $pageBoundary[0]['page_object']);
        $t->same(1, $pageBoundary[0]['page_number']);
        $t->same('page_tree_resources', $pageBoundary[0]['resources']['source']);
        $t->same(22, $pageBoundary[0]['resources']['resource_owner_object']);
        $t->same([0 => true, 2 => true], $freeObjects);
        $t->true(!str_contains($text, 'Stale plus-header xref page'));
        $t->true(!str_contains($text, 'Old plus-header trailer leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Plus Header'));
        $t->true(!str_contains($encodedFiles, 'stale-plus-header-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-plus-header-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
