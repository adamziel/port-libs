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

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Literal XRef Import) Tj T* (String-contained xref skipped) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Literal XRef Decoy Import) Tj T* (String xref root leak) Tj ET';
$currentXmp = $xmpPacket('Current Literal XRef Import');
$decoyXmp = $xmpPacket('Literal XRef Decoy Import');
$currentPayload = '<wp-export><post id="current-literal-string-xref-import"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-literal-string-xref-import"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Literal XRef Info) /Author (Current Literal Importer) >>');
$addObject(8, '<< /Names [(current-literal-string-xref-import.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-literal-string-xref-import.xml) /Desc (Current literal-string xref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Literal XRef Decoy Info) /Author (Literal Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-literal-string-xref-import.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-literal-string-xref-import.xml) /Desc (Decoy literal-string xref source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$literalXrefOffset = strlen($pdf) + 1;
$pdf .= "(xref\n"
    . "20 12\n";
for ($objectNumber = 20; $objectNumber <= 31; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? $xrefRow($offsets[$objectNumber])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>)\n"
    . "startxref\n{$literalXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $lines === ['Current Literal XRef Import', 'String-contained xref skipped']
    && ($metadata['title'] ?? null) === 'Current Literal XRef Import'
    && ($files[0]['name'] ?? null) === 'current-literal-string-xref-import.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-literal-string-xref-import.xml'];
$decoyExcluded = !str_contains($plainText, 'Literal XRef Decoy Import')
    && !str_contains($encodedMetadata, 'Literal XRef Decoy')
    && !str_contains($encodedFiles, 'decoy-literal-string-xref-import')
    && !str_contains($encodedAttachmentSummary, 'decoy-literal-string-xref-import');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected literal-string-contained classic xref table to be skipped before WordPress import.');
}

echo '<!-- markerpdf-classic-xref-literal-string-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-token-boundary-classic-xref-table-validation',
    'native_boundary' => 'direct startxref offsets inside PDF literal strings do not select classic xref tables before WordPress import',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'literal_string_xref_offset' => $literalXrefOffset,
    'literal_string_xref_decoy_skipped' => true,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'decoy_import_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
