<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Name-Offset XRef Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Name Offset XRef Decoy Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-offset xref page) Tj T* (Name-offset startxref repaired) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Name-offset xref decoy page) Tj T* (Name-offset root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-name-offset-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-name-offset-xref"/></wp-export>';
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
$addObject(7, '<< /Title (Current Name-Offset XRef Info) /Author (Current Name-Offset Importer) >>');
$addObject(8, '<< /Names [(current-name-offset-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-name-offset-xref.xml) /Desc (Current name-offset xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
$addObject(27, '<< /Title (Name Offset XRef Decoy Info) /Author (Name Offset Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-name-offset-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-name-offset-xref.xml) /Desc (Decoy name-offset xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$nameOffsetXrefOffset = strlen($pdf);
$pdf .= "/xref\n20 12\n";
foreach ([20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31] as $objectNumber) {
    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 65535, 'f');
}
$nameOffsetStartxrefOffset = $nameOffsetXrefOffset + 1;
$pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
    . "startxref\n{$nameOffsetStartxrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-name-offset-currentbase',
    'native_boundary' => 'classic xref rebuild repairs explicit startxref offsets that point at the x inside a /xref name-token decoy',
    'current_xref_offset' => $currentXrefOffset,
    'name_offset_startxref_offset' => $nameOffsetStartxrefOffset,
    'uses_current_page_text' => str_contains($plainText, 'Current name-offset xref page'),
    'repairs_name_token_startxref_offset' => !str_contains($plainText, 'Name-offset xref decoy page'),
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Name-Offset XRef Title',
    'info_title_current' => ($metadata['info']['Title'] ?? null) === 'Current Name-Offset XRef Info',
    'embedded_file_current' => ($files[0]['name'] ?? null) === 'current-name-offset-xref.xml',
    'excludes_decoy_metadata' => !str_contains($encodedMetadata, 'Name Offset XRef Decoy'),
    'excludes_decoy_attachment' => !str_contains($encodedFiles, 'decoy-name-offset-xref'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['uses_current_page_text']
    || !$summary['repairs_name_token_startxref_offset']
    || !$summary['metadata_title_current']
    || !$summary['info_title_current']
    || !$summary['embedded_file_current']
) {
    throw new RuntimeException('Expected classic xref rebuild to ignore the /xref name-token decoy.');
}

echo '<!-- markerpdf-xref-classic-rebuild-name-offset-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
