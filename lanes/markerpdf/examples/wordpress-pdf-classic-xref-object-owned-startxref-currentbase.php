<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Object-Owned StartXRef Import) Tj T* (Older valid pointer leaked) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Object-Owned StartXRef Import) Tj T* (Object-owned token only bounded rebuild) Tj ET';
$stalePayload = '<wp-export><post id="stale-object-owned-startxref-import"/></wp-export>';
$currentPayload = '<wp-export><post id="current-object-owned-startxref-import"/></wp-export>';
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
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($xmpPacket('Stale Object-Owned StartXRef Import')) . " >>\nstream\n" . $xmpPacket('Stale Object-Owned StartXRef Import') . "\nendstream");
$addObject(7, '<< /Title (Stale Object-Owned StartXRef Info) /Author (Stale Importer) >>');
$addObject(8, '<< /Names [(stale-object-owned-startxref-import.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-object-owned-startxref-import.xml) /Desc (Stale object-owned startxref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentXmp = $xmpPacket('Current Object-Owned StartXRef Import');
$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($currentContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(27, '<< /Title (Current Object-Owned StartXRef Info) /Author (Current Importer) >>');
$addObject(28, '<< /Names [(current-object-owned-startxref-import.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-object-owned-startxref-import.xml) /Desc (Current object-owned startxref source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
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
    . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n";
$objectOwnedTokenOffset = strlen($pdf);
$addObject(60, "<< /Note (damaged writer left startxref\n0\ninside an object after current xref) >>");

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $lines === ['Current Object-Owned StartXRef Import', 'Object-owned token only bounded rebuild']
    && ($metadata['title'] ?? null) === 'Current Object-Owned StartXRef Import'
    && ($metadata['info']['Title'] ?? null) === 'Current Object-Owned StartXRef Info'
    && ($files[0]['name'] ?? null) === 'current-object-owned-startxref-import.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-object-owned-startxref-import.xml'];
$staleExcluded = !str_contains($plainText, 'Stale Object-Owned StartXRef Import')
    && !str_contains($encodedMetadata, 'Stale Object-Owned')
    && !str_contains($encodedFiles, 'stale-object-owned-startxref-import')
    && !str_contains($encodedAttachmentSummary, 'stale-object-owned-startxref-import');

if (!$usesCurrentImport || !$staleExcluded) {
    throw new RuntimeException('Expected object-owned startxref token to bound, not select, classic xref rebuild.');
}

echo '<!-- markerpdf-classic-xref-object-owned-startxref-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-token-boundary-classic-xref-rebuild',
    'native_boundary' => 'object-owned startxref tokens bound classic xref repair scans without selecting stale offsets',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'object_owned_startxref_token_offset' => $objectOwnedTokenOffset,
    'current_revision_selected' => $usesCurrentImport,
    'stale_revision_excluded' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
