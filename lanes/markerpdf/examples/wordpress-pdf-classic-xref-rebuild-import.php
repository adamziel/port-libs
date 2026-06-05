<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObjectBody = static function (string $content): string {
    return "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Classic XRef Import) Tj T* (Array decoy ignored) Tj ET';
$arrayDecoyContent = 'BT /F1 12 Tf 72 720 Td (Array Decoy Import) Tj T* (Composite xref leak) Tj ET';
$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Classic XRef Import</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>';
$arrayDecoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Array Decoy Import</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>';
$currentPayload = '<wp-export><post id="current-classic-xref-import"/></wp-export>';
$arrayDecoyPayload = '<wp-export><post id="array-decoy-import"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));
$pdf = "%PDF-1.4\n";

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
$addObject(5, $streamObjectBody($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Classic XRef Info) /Author (Current Importer) >>');
$addObject(8, '<< /Names [(current-classic-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-classic-xref.xml) /Desc (Current classic xref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n";
$pdf .= $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObjectBody($arrayDecoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($arrayDecoyXmp) . " >>\nstream\n{$arrayDecoyXmp}\nendstream");
$addObject(27, '<< /Title (Array Decoy Info) /Author (Decoy Importer) >>');
$addObject(28, '<< /Names [(array-decoy.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (array-decoy.xml) /Desc (Array decoy source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($arrayDecoyPayload) . " >>\nstream\n{$arrayDecoyPayload}\nendstream");

$arrayDecoyXrefOffset = strlen($pdf) + strlen("[ /Preview ");
$pdf .= "[ /Preview xref\n20 12\n";
for ($objectNumber = 20; $objectNumber <= 31; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >> ]\nstartxref\n12\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

echo '<!-- markerpdf:pdf-classic-xref-rebuild ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'declared_startxref' => 12,
    'actual_xref_offset' => $xrefOffset,
    'array_decoy_xref_offset' => $arrayDecoyXrefOffset,
    'classic_xref_table_repaired' => true,
    'composite_xref_decoy_skipped' => !str_contains($plainText, 'Array Decoy Import')
        && (($metadata['title'] ?? null) === 'Current Classic XRef Import')
        && (($embeddedFiles[0]['name'] ?? null) === 'current-classic-xref.xml'),
    'array_decoy_stream_excluded' => !str_contains($plainText, 'Composite xref leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
