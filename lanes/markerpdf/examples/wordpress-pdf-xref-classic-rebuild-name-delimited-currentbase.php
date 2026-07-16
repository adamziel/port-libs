<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-delimited xref page) Tj T* (Name-delimited xref ignored) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Name-delimited xref decoy page) Tj T* (Delimited xref root leak) Tj ET';
$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Name-Delimited XRef Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Name Delimited XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$currentPayload = '<wp-export><post id="current-name-delimited-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-name-delimited-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Name-Delimited XRef Info) /Author (Current Name-Delimited Importer) >>');
$addObject(8, '<< /Names [(current-name-delimited-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-name-delimited-xref.xml) /Desc (Current name-delimited xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 24 0 R >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Name Delimited XRef Decoy Info) /Author (Name Delimited Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-name-delimited-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-name-delimited-xref.xml) /Desc (Decoy name-delimited xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$nameDelimitedXrefOffset = strlen($pdf);
$pdf .= "xref/Decoy\n20 12\n";
for ($objectNumber = 20; $objectNumber <= 31; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\nstartxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-name-delimited-currentbase',
    'native_boundary' => 'classic xref rebuild rejects name-delimited xref pseudo-table headers before WordPress import',
    'current_xref_offset' => $currentXrefOffset,
    'name_delimited_xref_offset' => $nameDelimitedXrefOffset,
    'uses_current_page_text' => str_contains($plainText, 'Current name-delimited xref page'),
    'skips_name_delimited_xref_table' => !str_contains($plainText, 'Name-delimited xref decoy page'),
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Name-Delimited XRef Title',
    'embedded_file_current' => ($files[0]['name'] ?? null) === 'current-name-delimited-xref.xml',
    'excludes_decoy_metadata' => !str_contains($encodedMetadata, 'Name Delimited XRef Decoy'),
    'excludes_decoy_attachment' => !str_contains($encodedFiles, 'decoy-name-delimited-xref'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['uses_current_page_text']
    || !$summary['skips_name_delimited_xref_table']
    || !$summary['metadata_title_current']
    || !$summary['embedded_file_current']
) {
    throw new RuntimeException('Expected name-delimited xref pseudo-table to be ignored during classic rebuild.');
}

echo '<!-- markerpdf-xref-classic-rebuild-name-delimited-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
