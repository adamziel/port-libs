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

$currentXmp = $xmpPacket('Current Zero-Count XRef Title');
$decoyXmp = $xmpPacket('Zero-Count XRef Decoy Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current zero-count xref page) Tj T* (Zero-count table rejected) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Zero-count decoy xref page) Tj T* (Empty subsection root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-zero-count-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-zero-count-xref"/></wp-export>';
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
$addObject(7, '<< /Title (Current Zero-Count XRef Info) /Author (Current Zero Importer) >>');
$addObject(8, '<< /Names [(current-zero-count-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-zero-count-xref.xml) /Desc (Current zero-count xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow($offsets[7])
    . $xrefRow($offsets[8])
    . $xrefRow($offsets[9])
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Zero-Count XRef Decoy Info) /Author (Zero Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-zero-count-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-zero-count-xref.xml) /Desc (Decoy zero-count xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$zeroCountXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "20 0\n"
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
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

$usesCurrent = $lines === ['Current zero-count xref page', 'Zero-count table rejected']
    && ($metadata['title'] ?? null) === 'Current Zero-Count XRef Title'
    && ($metadata['info']['Title'] ?? null) === 'Current Zero-Count XRef Info'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-zero-count-xref.xml'
    && ($embeddedFiles[0]['checksum_matches'] ?? null) === true
    && ($attachmentSummary['filenames'] ?? []) === ['current-zero-count-xref.xml'];
$decoyExcluded = !str_contains($plainText, 'Zero-count decoy xref page')
    && !str_contains($plainText, 'Empty subsection root leak')
    && !str_contains($encodedMetadata, 'Zero-Count XRef Decoy')
    && !str_contains($encodedFiles, 'decoy-zero-count-xref')
    && !str_contains($encodedAttachmentSummary, 'decoy-zero-count-xref');

if (!$usesCurrent || !$decoyExcluded) {
    throw new RuntimeException('Expected zero-count classic xref subsection to be rejected before WordPress import.');
}

echo '<!-- markerpdf-classic-xref-zero-count-subsection-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-subsection-count-boundary',
    'native_boundary' => 'classic xref rebuild rejects zero-count subsections before root, metadata, and EmbeddedFiles selection',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'zero_count_xref_offset' => $zeroCountXrefOffset,
    'rejects_zero_count_subsection' => true,
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
