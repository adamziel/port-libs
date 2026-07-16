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

$staleXmp = $xmpPacket('Stale Form-Feed XRef Import');
$currentXmp = $xmpPacket('Current Form-Feed XRef Import');
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Form Feed Import) Tj T* (Earlier form-feed root leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Form Feed Import) Tj T* (Form-feed xref whitespace repaired) Tj ET';
$stalePayload = '<wp-export><post id="stale-formfeed-xref-import"/></wp-export>';
$currentPayload = '<wp-export><post id="current-formfeed-xref-import"/></wp-export>';
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
$addObject(5, $streamObject($staleContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$addObject(7, '<< /Title (Stale Form-Feed XRef Info) /Author (Stale Form Importer) >>');
$addObject(8, '<< /Names [(stale-formfeed-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-formfeed-xref.xml) /Desc (Stale form-feed xref import) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
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
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($currentContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(27, '<< /Title (Current Form-Feed XRef Info) /Author (Current Form Importer) >>');
$addObject(28, '<< /Names [(current-formfeed-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-formfeed-xref.xml) /Desc (Current form-feed xref import) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\f"
    . "20 12\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[26])
    . $xrefRow($offsets[27])
    . $xrefRow($offsets[28])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[30])
    . $xrefRow($offsets[31])
    . "trailer\f<< /Size 32 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

$currentSelected = $lines === ['Current Form Feed Import', 'Form-feed xref whitespace repaired']
    && ($metadata['title'] ?? null) === 'Current Form-Feed XRef Import'
    && ($metadata['info']['Title'] ?? null) === 'Current Form-Feed XRef Info'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-formfeed-xref.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-formfeed-xref.xml'];
$staleExcluded = !str_contains($plainText, 'Stale Form Feed Import')
    && !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Stale Form-Feed')
    && !str_contains(json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '', 'stale-formfeed-xref')
    && !str_contains(json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '', 'stale-formfeed-xref');

if (!$currentSelected || !$staleExcluded) {
    throw new RuntimeException('Expected form-feed whitespace xref rebuild to keep the current WordPress import root.');
}

echo '<!-- markerpdf-classic-xref-formfeed-whitespace-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-formfeed-whitespace',
    'native_boundary' => 'classic xref rebuild accepts form-feed PDF whitespace after xref and trailer keywords before WordPress import selection',
    'paragraphs' => $lines,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $embeddedFiles[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'formfeed_whitespace_accepted' => true,
    'current_classic_xref_import_kept' => $currentSelected,
    'stale_prev_import_excluded' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
