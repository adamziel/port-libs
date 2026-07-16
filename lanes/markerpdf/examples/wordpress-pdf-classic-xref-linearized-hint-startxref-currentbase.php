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

$currentXmp = $xmpPacket('Current Hint-Bounded XRef Import');
$hintDecoyXmp = $xmpPacket('Hint Startxref Decoy Import');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Hint-Bounded Import) Tj T* (Linearized hint startxref skipped) Tj ET';
$hintDecoyContent = 'BT /F1 12 Tf 72 720 Td (Hint Startxref Decoy Import) Tj T* (Hint-range startxref leak) Tj ET';
$currentPayload = '<wp-export><post id="current-hint-bounded-import"/></wp-export>';
$hintDecoyPayload = '<wp-export><post id="decoy-hint-bounded-import"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, '<< /Linearized 1 /L 0000000000 /H [ 0000000000 0000000000 ] /O 4 /E 0 /N 1 /T 0 >>');
$addObject(2, '<< /Type /Catalog /Pages 3 0 R /Metadata 7 0 R /Names << /EmbeddedFiles 9 0 R >> >>');
$addObject(3, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(4, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>');
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, $streamObject($currentContent));
$addObject(7, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, '<< /Title (Current Hint-Bounded Info) /Author (Current Hint Importer) >>');
$addObject(9, '<< /Names [(current-hint-bounded-import.xml) 10 0 R] >>');
$addObject(10, '<< /Type /Filespec /F (current-hint-bounded-import.xml) /Desc (Current hint-bounded import source) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n0 12\n";
for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
    $pdf .= $objectNumber === 0
        ? $xrefRow(0, 65535, 'f')
        : $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
}
$pdf .= "trailer\n<< /Size 32 /Root 2 0 R /Info 8 0 R >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($hintDecoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($hintDecoyXmp) . " >>\nstream\n{$hintDecoyXmp}\nendstream");
$addObject(27, '<< /Title (Hint Startxref Decoy Info) /Author (Hint Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-hint-bounded-import.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-hint-bounded-import.xml) /Desc (Decoy hint-bounded import source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($hintDecoyPayload) . " >>\nstream\n{$hintDecoyPayload}\nendstream");

$hintDecoyXrefOffset = strlen($pdf);
$pdf .= "xref\n20 12\n";
for ($objectNumber = 20; $objectNumber <= 31; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber] ?? 0, 0, isset($offsets[$objectNumber]) ? 'n' : 'f');
}
$pdf .= "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n";
$hintStart = strlen($pdf);
$pdf .= "startxref\n{$hintDecoyXrefOffset}\n%%EOF";
$hintLength = strlen($pdf) - $hintStart;
$pdf = str_replace(
    '0000000000 /H [ 0000000000 0000000000',
    sprintf('%010d /H [ %010d %010d', strlen($pdf), $hintStart, $hintLength),
    $pdf
);

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $lines === ['Current Hint-Bounded Import', 'Linearized hint startxref skipped']
    && ($metadata['title'] ?? null) === 'Current Hint-Bounded XRef Import'
    && ($files[0]['name'] ?? null) === 'current-hint-bounded-import.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-hint-bounded-import.xml'];
$decoyExcluded = !str_contains($plainText, 'Hint Startxref Decoy Import')
    && !str_contains($encodedMetadata, 'Hint Startxref Decoy')
    && !str_contains($encodedFiles, 'decoy-hint-bounded-import')
    && !str_contains($encodedAttachmentSummary, 'decoy-hint-bounded-import');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected linearized hint-range startxref token to be skipped before WordPress import.');
}

echo '<!-- markerpdf-classic-xref-linearized-hint-startxref-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-linearized-hint-startxref-boundary',
    'native_boundary' => 'classic xref rebuild skips startxref tokens inside linearized /H hint-table byte ranges before WordPress import',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'hint_decoy_xref_offset' => $hintDecoyXrefOffset,
    'hint_range' => [$hintStart, $hintLength],
    'linearized_hint_startxref_skipped' => true,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'hint_decoy_import_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
