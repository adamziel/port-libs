<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};
$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale WordPress import page) Tj T* (Old startxref pointer) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current missing-final-startxref import) Tj T* (Recovered before EOF) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Post EOF missing-final-startxref decoy) Tj T* (Should not import) Tj ET';
$stalePayload = '<wp-export><post id="stale-import"/></wp-export>';
$currentPayload = '<wp-export><post id="current-missing-final-startxref-import"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-import"/></wp-export>';
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
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($xmpPacket('Stale Import Title')) . " >>\nstream\n" . $xmpPacket('Stale Import Title') . "\nendstream");
$addObject(7, '<< /Title (Stale Import Info) /Author (Stale Importer) >>');
$addObject(8, '<< /Names [(stale-import.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-import.xml) /Desc (Stale import source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$priorXrefOffset = strlen($pdf);
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
    . "trailer\n<< /Size 11 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n{$priorXrefOffset}\n%%EOF\n";

$currentXmp = $xmpPacket('Current Missing Final Startxref Import');
$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($currentContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(27, '<< /Title (Current Missing Final Startxref Info) /Author (Current Importer) >>');
$addObject(28, '<< /Names [(current-missing-final-startxref-import.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-missing-final-startxref-import.xml) /Desc (Current missing final startxref import source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "20 12\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[26])
    . $xrefRow($offsets[27])
    . $xrefRow($offsets[28])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[30])
    . $xrefRow($offsets[31])
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
    . "%%EOF\n";
$finalEofOffset = strrpos($pdf, '%%EOF');

$decoyXmp = $xmpPacket('Post EOF Decoy Import');
$addObject(40, '<< /Type /Catalog /Pages 41 0 R /Metadata 46 0 R /Names << /EmbeddedFiles 48 0 R >> >>');
$addObject(41, '<< /Type /Pages /Kids [42 0 R] /Count 1 >>');
$addObject(42, '<< /Type /Page /Parent 41 0 R /Resources << /Font << /F1 43 0 R >> >> /Contents 44 0 R >>');
$addObject(43, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(44, $streamObject($decoyContent));
$addObject(46, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(47, '<< /Title (Post EOF Decoy Info) /Author (Decoy Importer) >>');
$addObject(48, '<< /Names [(decoy-import.xml) 50 0 R] >>');
$addObject(50, '<< /Type /Filespec /F (decoy-import.xml) /Desc (Decoy import source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
$addObject(51, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
$decoyXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "40 12\n"
    . $xrefRow($offsets[40])
    . $xrefRow($offsets[41])
    . $xrefRow($offsets[42])
    . $xrefRow($offsets[43])
    . $xrefRow($offsets[44])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[46])
    . $xrefRow($offsets[47])
    . $xrefRow($offsets[48])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[50])
    . $xrefRow($offsets[51])
    . "trailer\n<< /Size 52 /Root 40 0 R /Info 47 0 R >>\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

$usesCurrentImport = $lines === ['Current missing-final-startxref import', 'Recovered before EOF']
    && ($metadata['title'] ?? null) === 'Current Missing Final Startxref Import'
    && ($files[0]['name'] ?? null) === 'current-missing-final-startxref-import.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-missing-final-startxref-import.xml'];
$staleAndDecoyExcluded = !str_contains($plainText, 'Stale WordPress import')
    && !str_contains($plainText, 'Post EOF missing-final-startxref decoy')
    && !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Stale Import')
    && !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Post EOF Decoy')
    && !str_contains(json_encode($files, JSON_UNESCAPED_SLASHES) ?: '', 'stale-import')
    && !str_contains(json_encode($files, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-import')
    && !str_contains(json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '', 'stale-import')
    && !str_contains(json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-import');

if (!$usesCurrentImport || !$staleAndDecoyExcluded) {
    throw new RuntimeException('Expected missing final startxref rebuild to select the current WordPress import revision.');
}

echo '<!-- markerpdf-xref-classic-missing-final-startxref-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-rebuild',
    'support_component' => 'native-pdf-classic-rebuild-boundary',
    'native_boundary' => 'older valid startxref does not hide a later classic xref before EOF when final startxref is missing',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'prior_xref_offset' => $priorXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'final_eof_offset' => $finalEofOffset,
    'post_eof_decoy_xref_offset' => $decoyXrefOffset,
    'current_import_kept' => $usesCurrentImport,
    'stale_and_post_eof_decoys_excluded' => $staleAndDecoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
