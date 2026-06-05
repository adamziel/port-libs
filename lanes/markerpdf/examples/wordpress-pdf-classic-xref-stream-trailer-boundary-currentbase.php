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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Stream-Trailer XRef Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stream Trailer Decoy Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Stream-Trailer XRef Import) Tj T* (Stream trailer boundary kept) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Stream Trailer Decoy Import) Tj T* (Stream-owned trailer leak) Tj ET';
$currentPayload = '<wp-export><post id="current-stream-trailer-xref-import"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-stream-trailer-xref-import"/></wp-export>';
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
$addObject(7, '<< /Title (Current Stream-Trailer XRef Info) /Author (Current Importer) >>');
$addObject(8, '<< /Names [(current-stream-trailer-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-stream-trailer-xref.xml) /Desc (Current stream-trailer xref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Stream Trailer Decoy Info) /Author (Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-stream-trailer-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-stream-trailer-xref.xml) /Desc (Decoy stream-trailer xref source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n0 32\n";
for ($objectNumber = 0; $objectNumber <= 31; $objectNumber++) {
    $pdf .= $objectNumber === 0
        ? $xrefRow(0, 65535, 'f')
        : $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
}

$fakeTrailer = "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";
$fakeTrailerOffset = strlen($pdf) + strlen("40 0 obj\n<< /Length " . strlen($fakeTrailer) . " >>\nstream\n");
$pdf .= "40 0 obj\n<< /Length " . strlen($fakeTrailer) . " >>\nstream\n"
    . $fakeTrailer
    . "endstream\nendobj\n";
$realTrailerOffset = strlen($pdf);
$pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

$usesCurrentImport = $lines === ['Current Stream-Trailer XRef Import', 'Stream trailer boundary kept']
    && ($metadata['title'] ?? null) === 'Current Stream-Trailer XRef Import'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-stream-trailer-xref.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-stream-trailer-xref.xml'];
$decoyExcluded = !str_contains($plainText, 'Stream Trailer Decoy Import')
    && !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Stream Trailer Decoy')
    && !str_contains(json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-stream-trailer-xref')
    && !str_contains(json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-stream-trailer-xref');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected stream-owned classic xref trailer bytes to be skipped before WordPress import.');
}

echo '<!-- markerpdf-xref-classic-rebuild-stream-trailer-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-trailer-owner-boundary',
    'native_boundary' => 'classic xref rebuild skips trailer dictionaries inside direct stream object bodies before selecting WordPress import roots',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'fake_stream_trailer_offset' => $fakeTrailerOffset,
    'real_trailer_offset' => $realTrailerOffset,
    'stream_owned_trailer_skipped' => true,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'decoy_stream_trailer_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
