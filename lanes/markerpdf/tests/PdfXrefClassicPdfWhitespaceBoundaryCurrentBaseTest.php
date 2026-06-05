<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicPdfWhitespaceBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };

    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $nulDelimitedXrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d\0%05d\0%s \n", $offset, $generation, $state);

    $staleXmp = $xmpPacket('Stale PDF-Whitespace XRef Title');
    $currentXmp = $xmpPacket('Current PDF-Whitespace XRef Title');
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale PDF-whitespace xref page) Tj T* (NUL whitespace root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current PDF-whitespace xref page) Tj T* (NUL whitespace table repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-pdf-whitespace-xref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-pdf-whitespace-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($staleContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale PDF-Whitespace XRef Info) /Author (Stale Importer) >>');
    $addObject(8, '<< /Names [(stale-pdf-whitespace-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-pdf-whitespace-xref.xml) /Desc (Stale PDF-whitespace xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
    $addObject(24, $streamObject($currentContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current PDF-Whitespace XRef Info) /Author (Current Importer) >>');
    $addObject(28, '<< /Names [(current-pdf-whitespace-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-pdf-whitespace-xref.xml) /Desc (Current PDF-whitespace xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\0\n"
        . "20\0 12\n"
        . $nulDelimitedXrefRow($offsets[20])
        . $nulDelimitedXrefRow($offsets[21])
        . $nulDelimitedXrefRow($offsets[22])
        . $nulDelimitedXrefRow($offsets[23])
        . $nulDelimitedXrefRow($offsets[24])
        . $nulDelimitedXrefRow(0, 65535, 'f')
        . $nulDelimitedXrefRow($offsets[26])
        . $nulDelimitedXrefRow($offsets[27])
        . $nulDelimitedXrefRow($offsets[28])
        . $nulDelimitedXrefRow(0, 65535, 'f')
        . $nulDelimitedXrefRow($offsets[30])
        . $nulDelimitedXrefRow($offsets[31])
        . "trailer\0<< /Size 32 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset];
};

return [
    'accepts PDF NUL whitespace in rebuilt classic xref tables before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicPdfWhitespaceBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset] = $xrefClassicPdfWhitespaceBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current PDF-whitespace xref page', 'NUL whitespace table repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current PDF-whitespace xref page', 'NUL whitespace table repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current PDF-whitespace xref page\nNUL whitespace table repaired", $text);
        $t->same("Current PDF-whitespace xref page\nNUL whitespace table repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current PDF-Whitespace XRef Title', $metadata['title']);
        $t->same('Current PDF-Whitespace XRef Info', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-pdf-whitespace-xref.xml', $files[0]['name']);
        $t->same('current-pdf-whitespace-xref.xml', $files[0]['filename']);
        $t->same('Current PDF-whitespace xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-pdf-whitespace-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale PDF-whitespace xref page'));
        $t->true(!str_contains($text, 'NUL whitespace root leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale PDF-Whitespace'));
        $t->true(!str_contains($encodedFiles, 'stale-pdf-whitespace-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-pdf-whitespace-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
