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

$currentXmp = $xmpPacket('Current Header-Garbage XRef Title');
$decoyXmp = $xmpPacket('Header Garbage Decoy Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current header-garbage xref page) Tj T* (Malformed subsection header skipped) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Header garbage decoy page) Tj T* (Malformed header root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-header-garbage-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-header-garbage-xref"/></wp-export>';
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
$addObject(7, '<< /Title (Current Header-Garbage XRef Info) /Author (Current Header Importer) >>');
$addObject(8, '<< /Names [(current-header-garbage-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-header-garbage-xref.xml) /Desc (Current header-garbage xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
$addObject(27, '<< /Title (Header Garbage Decoy Info) /Author (Header Garbage Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-header-garbage-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-header-garbage-xref.xml) /Desc (Decoy header-garbage xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$garbageHeaderXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "not a valid xref subsection header before later-looking rows\n"
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
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$paragraphs = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $paragraphs === ['Current header-garbage xref page', 'Malformed subsection header skipped']
    && ($metadata['title'] ?? null) === 'Current Header-Garbage XRef Title'
    && ($metadata['info']['Title'] ?? null) === 'Current Header-Garbage XRef Info'
    && ($files[0]['name'] ?? null) === 'current-header-garbage-xref.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-header-garbage-xref.xml'];
$decoyExcluded = !str_contains($plainText, 'Header garbage decoy page')
    && !str_contains($plainText, 'Malformed header root leak')
    && !str_contains($encodedMetadata, 'Header Garbage Decoy')
    && !str_contains($encodedFiles, 'decoy-header-garbage-xref')
    && !str_contains($encodedAttachmentSummary, 'decoy-header-garbage-xref');

if (!$usesCurrentImport || !$decoyExcluded) {
    throw new RuntimeException('Expected malformed leading classic xref subsection headers to be rejected before WordPress import.');
}

echo '<!-- markerpdf-xref-classic-rebuild-header-garbage-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-xref-table-row-parser',
    'native_boundary' => 'classic xref sections with malformed leading subsection headers are rejected before rebuild selects import roots',
    'paragraphs' => $paragraphs,
    'metadata_title' => $metadata['title'] ?? null,
    'embedded_file' => $files[0]['name'] ?? null,
    'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    'current_xref_offset' => $currentXrefOffset,
    'garbage_header_xref_offset' => $garbageHeaderXrefOffset,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'decoy_xref_section_rejected' => $decoyExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
