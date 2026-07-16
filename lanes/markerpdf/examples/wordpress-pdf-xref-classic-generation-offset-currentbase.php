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

$currentXmp = $xmpPacket('Current Generation-Offset XRef Title');
$decoyXmp = $xmpPacket('Generation-Offset Decoy XRef Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation-offset xref page) Tj T* (Generation-offset rows repaired) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Generation-offset decoy xref page) Tj T* (Wrong generation root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-generation-offset-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-generation-offset-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber][$generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, $streamObject($currentContent));
$addObject(6, 0, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, 0, '<< /Title (Current Generation-Offset XRef Info) /Author (Current Generation Importer) >>');
$addObject(8, 0, '<< /Names [(current-generation-offset-xref.xml) 9 0 R] >>');
$addObject(9, 0, '<< /Type /Filespec /F (current-generation-offset-xref.xml) /Desc (Current generation-offset xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Metadata 6 1 R /Names << /EmbeddedFiles 8 1 R >> >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
$addObject(5, 1, $streamObject($decoyContent));
$addObject(6, 1, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(7, 1, '<< /Title (Generation-Offset Decoy XRef Info) /Author (Decoy Generation Importer) >>');
$addObject(8, 1, '<< /Names [(decoy-generation-offset-xref.xml) 9 1 R] >>');
$addObject(9, 1, '<< /Type /Filespec /F (decoy-generation-offset-xref.xml) /Desc (Decoy generation-offset xref attachment) /AFRelationship /Source /EF << /F 10 1 R >> >>');
$addObject(10, 1, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1][1], 0)
    . $xrefRow($offsets[2][1], 0)
    . $xrefRow($offsets[3][1], 0)
    . $xrefRow($offsets[4][0], 0)
    . $xrefRow($offsets[5][1], 0)
    . $xrefRow($offsets[6][1], 0)
    . $xrefRow($offsets[7][1], 0)
    . $xrefRow($offsets[8][1], 0)
    . $xrefRow($offsets[9][1], 0)
    . $xrefRow($offsets[10][1], 0)
    . "trailer\n<< /Size 11 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrent = $lines === ['Current generation-offset xref page', 'Generation-offset rows repaired']
    && ($metadata['title'] ?? null) === 'Current Generation-Offset XRef Title'
    && ($metadata['info']['Title'] ?? null) === 'Current Generation-Offset XRef Info'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-generation-offset-xref.xml'
    && ($embeddedFiles[0]['checksum_matches'] ?? null) === true
    && ($attachmentSummary['filenames'] ?? []) === ['current-generation-offset-xref.xml'];
$decoyExcluded = !str_contains($plainText, 'Generation-offset decoy xref page')
    && !str_contains($plainText, 'Wrong generation root leak')
    && !str_contains($encodedMetadata, 'Generation-Offset Decoy')
    && !str_contains($encodedFiles, 'decoy-generation-offset-xref')
    && !str_contains($encodedAttachmentSummary, 'decoy-generation-offset-xref');

if (!$usesCurrent || !$decoyExcluded) {
    throw new RuntimeException('Expected classic xref generation/offset mismatch repair before WordPress import.');
}

echo '<!-- markerpdf-classic-xref-generation-offset-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-generation-offset-boundary',
    'native_boundary' => 'classic xref rebuild repairs same-object wrong-generation explicit rows before root, metadata, and EmbeddedFiles selection',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'xref_offset' => $xrefOffset,
    'wrong_generation_offsets_repaired' => true,
    'current_classic_xref_import_kept' => $usesCurrent,
    'decoy_import_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
