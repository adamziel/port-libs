<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicFallbackTrailerStreamBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };

    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";

    $currentXmp = $xmpPacket('Current Fallback Trailer Stream Title');
    $decoyXmp = $xmpPacket('Stream-Owned Trailer Decoy Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current fallback trailer stream page) Tj T* (Top-level trailer kept) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Stream-owned fallback trailer decoy page) Tj T* (Stream trailer root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-fallback-trailer-stream"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-fallback-trailer-stream"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($currentContent));
    $addObject(6, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Fallback Trailer Stream Info) /Author (Current Trailer Importer) >>');
    $addObject(8, '<< /Names [(current-fallback-trailer-stream.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-fallback-trailer-stream.xml) /Desc (Current fallback trailer stream attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $topLevelTrailerOffset = strlen($pdf);
    $pdf .= "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R >>\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Stream-Owned Trailer Decoy Info) /Author (Stream Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-fallback-trailer-stream.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-fallback-trailer-stream.xml) /Desc (Decoy fallback trailer stream attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $streamOwnedTrailerOffset = $addObject(
        40,
        $streamObject("damaged producer payload before pseudo trailer\ntrailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\ndamaged producer payload after pseudo trailer")
    );

    return [$pdf, $currentPayload, strtolower($currentChecksum), $topLevelTrailerOffset, $streamOwnedTrailerOffset];
};

return [
    'skips stream-owned fallback trailer dictionaries before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefClassicFallbackTrailerStreamBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $topLevelTrailerOffset, $streamOwnedTrailerOffset] = $xrefClassicFallbackTrailerStreamBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($topLevelTrailerOffset > 0);
        $t->true($streamOwnedTrailerOffset > $topLevelTrailerOffset);
        $t->same(['Current fallback trailer stream page', 'Top-level trailer kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current fallback trailer stream page', 'Top-level trailer kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current fallback trailer stream page\nTop-level trailer kept", $text);
        $t->same("Current fallback trailer stream page\nTop-level trailer kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Fallback Trailer Stream Title', $metadata['title']);
        $t->same('Current Fallback Trailer Stream Info', $metadata['info']['Title']);
        $t->same('Current Trailer Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-fallback-trailer-stream.xml', $files[0]['name']);
        $t->same('current-fallback-trailer-stream.xml', $files[0]['filename']);
        $t->same('Current fallback trailer stream attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-fallback-trailer-stream.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stream-owned fallback trailer decoy page'));
        $t->true(!str_contains($text, 'Stream trailer root leak'));
        $t->true(!str_contains($encodedMetadata, 'Stream-Owned Trailer Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-fallback-trailer-stream'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-fallback-trailer-stream'));
        $t->true(!str_contains($text, "\0"));
    },
];
