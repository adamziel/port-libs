<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Post-StartXRef Trailer Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Post StartXRef Trailer Decoy Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Post-StartXRef Trailer Import) Tj T* (Late trailer boundary kept) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Post StartXRef Trailer Decoy Import) Tj T* (Late trailer root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-post-startxref-trailer-xref-import"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-post-startxref-trailer-xref-import"/></wp-export>';
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
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Post-StartXRef Trailer Info) /Author (Current Importer) >>');
$addObject(8, '<< /Names [(current-post-startxref-trailer-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-post-startxref-trailer-xref.xml) /Desc (Current post-startxref trailer source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n";
for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $objectNumber === 0
        ? $xrefRow(0, 65535, 'f')
        : $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Post StartXRef Trailer Decoy Info) /Author (Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-post-startxref-trailer-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-post-startxref-trailer-xref.xml) /Desc (Decoy post-startxref trailer source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$postStartxrefXrefOffset = strlen($pdf);
$pdf .= "xref\n20 12\n"
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
    . "startxref\n{$postStartxrefXrefOffset}\n%%EOF\n"
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $lines === ['Current Post-StartXRef Trailer Import', 'Late trailer boundary kept']
    && ($metadata['title'] ?? null) === 'Current Post-StartXRef Trailer Import'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-post-startxref-trailer-xref.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-post-startxref-trailer-xref.xml'];
$decoyExcluded = !str_contains($plainText, 'Post StartXRef Trailer Decoy Import')
    && !str_contains($encodedMetadata, 'Post StartXRef Trailer Decoy')
    && !str_contains($encodedFiles, 'decoy-post-startxref-trailer-xref')
    && !str_contains($encodedAttachmentSummary, 'decoy-post-startxref-trailer-xref');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected post-startxref classic xref trailer bytes to be rejected before WordPress import.');
}

echo '<!-- markerpdf-classic-xref-post-startxref-trailer-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-section-boundary',
    'native_boundary' => 'classic xref rebuild rejects trailer dictionaries that appear after startxref or %%EOF before WordPress import',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'post_startxref_xref_offset' => $postStartxrefXrefOffset,
    'post_startxref_trailer_rejected' => true,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'decoy_post_startxref_trailer_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
