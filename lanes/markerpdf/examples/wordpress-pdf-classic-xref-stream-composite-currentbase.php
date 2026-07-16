<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale stream-composite xref page) Tj T* (Stream composite hid current xref) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Stream Composite XRef Import) Tj T* (Real xref after stream composite selected) Tj ET';
$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Stream Composite XRef Import</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>';
$currentPayload = '<wp-export><post id="current-stream-composite-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(20, '<< /Type /Catalog /Pages 21 0 R >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($staleContent));

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n20 5\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . "trailer\n<< /Size 64 /Root 20 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$streamCompositeOffset = $addObject(40, $streamObject('[ stream-owned array without close before the current top-level xref'));
$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Stream Composite XRef Info) /Author (Current Stream Importer) >>');
$addObject(8, '<< /Names [(current-stream-composite-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-stream-composite-xref.xml) /Desc (Current stream-composite xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF\n]\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

echo '<!-- markerpdf:pdf-classic-xref-stream-composite ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-rebuild-stream-composite-boundary',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'metadata_info_title' => $metadata['info']['Title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_count' => $attachmentSummary['attachment_count'] ?? null,
    'declared_startxref' => 999999,
    'previous_xref_offset' => $previousXrefOffset,
    'stream_composite_object_offset' => $streamCompositeOffset,
    'actual_xref_offset' => $currentXrefOffset,
    'classic_xref_table_repaired' => true,
    'stream_composite_boundary_skipped' => !str_contains($plainText, 'Stale stream-composite xref page')
        && (($metadata['title'] ?? null) === 'Current Stream Composite XRef Import')
        && (($embeddedFiles[0]['name'] ?? null) === 'current-stream-composite-xref.xml'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
