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

$staleXmp = $xmpPacket('Stale Comment-Delimiter XRef Import');
$currentXmp = $xmpPacket('Current Comment-Delimiter XRef Import');
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Comment-Delimiter XRef Import) Tj T* (Comment delimiter root leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Comment-Delimiter XRef Import) Tj T* (Xref comment delimiter repaired) Tj ET';
$stalePayload = '<wp-export><post id="stale-comment-delimiter-xref-import"/></wp-export>';
$currentPayload = '<wp-export><post id="current-comment-delimiter-xref-import"/></wp-export>';
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
$addObject(7, '<< /Title (Stale Comment-Delimiter XRef Info) /Author (Stale Comment Importer) >>');
$addObject(8, '<< /Names [(stale-comment-delimiter-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-comment-delimiter-xref.xml) /Desc (Stale comment-delimiter xref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 11\n"
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
$addObject(27, '<< /Title (Current Comment-Delimiter XRef Info) /Author (Current Comment Importer) >>');
$addObject(28, '<< /Names [(current-comment-delimiter-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-comment-delimiter-xref.xml) /Desc (Current comment-delimiter xref source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref% current table comment is PDF whitespace\n"
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
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentImport = $lines === ['Current Comment-Delimiter XRef Import', 'Xref comment delimiter repaired']
    && ($metadata['title'] ?? null) === 'Current Comment-Delimiter XRef Import'
    && ($metadata['info']['Title'] ?? null) === 'Current Comment-Delimiter XRef Info'
    && ($embeddedFiles[0]['name'] ?? null) === 'current-comment-delimiter-xref.xml'
    && ($attachmentSummary['filenames'] ?? []) === ['current-comment-delimiter-xref.xml'];
$staleExcluded = !str_contains($plainText, 'Stale Comment-Delimiter XRef Import')
    && !str_contains($encodedMetadata, 'Stale Comment-Delimiter')
    && !str_contains($encodedFiles, 'stale-comment-delimiter-xref')
    && !str_contains($encodedAttachmentSummary, 'stale-comment-delimiter-xref');

if (!$usesCurrentImport || !$staleExcluded) {
    throw new RuntimeException('Expected xref comment delimiter boundary to rebuild to the current classic table.');
}

echo '<!-- markerpdf-xref-classic-comment-delimiter-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-classic-xref-comment-delimiter-boundary',
    'native_boundary' => 'classic xref rebuild accepts a PDF comment delimiter immediately after the xref keyword before WordPress import',
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'gutenberg_paragraphs' => $lines,
    'current_classic_xref_import_kept' => $usesCurrentImport,
    'comment_delimiter_xref_accepted' => true,
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Comment-Delimiter XRef Import',
    'embedded_file_current' => ($embeddedFiles[0]['name'] ?? null) === 'current-comment-delimiter-xref.xml',
    'stale_xref_excluded' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($lines[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($lines[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
