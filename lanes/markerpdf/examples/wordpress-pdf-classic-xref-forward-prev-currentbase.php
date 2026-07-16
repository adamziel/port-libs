<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Forward Prev XRef Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$decoyXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Forward Prev Decoy Import</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta>';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Forward Prev Import) Tj T* (Backward xref dependency kept) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Forward Prev Decoy Import) Tj T* (Post EOF Prev import leak) Tj ET';
$currentPayload = '<wp-export><post id="current-forward-prev-import"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-forward-prev-import"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Forward Prev Info) /Author (Current Importer) >>');
$addObject(8, '<< /Names [(current-forward-prev-import.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-forward-prev-import.xml) /Desc (Current forward-prev source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow($offsets[7])
    . $xrefRow($offsets[8])
    . $xrefRow($offsets[9])
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "1 1\n"
    . $xrefRow($offsets[1])
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R /Prev 0000000000 >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($decoyContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(7, '<< /Title (Forward Prev Decoy Info) /Author (Decoy Importer) >>');
$addObject(8, '<< /Names [(decoy-forward-prev-import.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (decoy-forward-prev-import.xml) /Desc (Forward Prev decoy source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$forwardPrevXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 0, 'f')
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
$pdf = str_replace('/Prev 0000000000', '/Prev ' . sprintf('%010d', $forwardPrevXrefOffset), $pdf);

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

$usesCurrentImport = $lines === ['Current Forward Prev Import', 'Backward xref dependency kept']
    && ($metadata['title'] ?? null) === 'Current Forward Prev XRef Import'
    && ($files[0]['name'] ?? null) === 'current-forward-prev-import.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-forward-prev-import.xml'];
$decoyExcluded = !str_contains($plainText, 'Forward Prev Decoy Import')
    && !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Forward Prev Decoy')
    && !str_contains(json_encode($files, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-forward-prev-import')
    && !str_contains(json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '', 'decoy-forward-prev-import');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected forward classic /Prev pointers to repair to the prior xref section before WordPress import.');
}

echo '<!-- markerpdf-xref-classic-forward-prev-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-prev-chain-boundary',
    'native_boundary' => 'classic xref rebuild repairs forward /Prev pointers to the prior section before selecting WordPress import objects',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'forward_prev_xref_offset' => $forwardPrevXrefOffset,
    'forward_prev_repaired_to_prior_section' => true,
    'current_import_kept' => $usesCurrentImport,
    'post_eof_prev_decoy_excluded' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
