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

$staleXmp = $xmpPacket('Stale Partial-Trailing XRef Title');
$currentXmp = $xmpPacket('Current Partial-Trailing XRef Title');
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale partial-trailing xref page) Tj T* (Partial trailing subsection leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current partial-trailing xref page) Tj T* (Partial trailing subsection ignored) Tj ET';
$stalePayload = '<wp-export><post id="stale-partial-trailing-xref"/></wp-export>';
$currentPayload = '<wp-export><post id="current-partial-trailing-xref"/></wp-export>';
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

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($staleContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$addObject(27, '<< /Title (Stale Partial-Trailing XRef Info) /Author (Stale Partial Importer) >>');
$addObject(28, '<< /Names [(stale-partial-trailing-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (stale-partial-trailing-xref.xml) /Desc (Stale partial-trailing xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
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
    . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Partial-Trailing XRef Info) /Author (Current Partial Importer) >>');
$addObject(8, '<< /Names [(current-partial-trailing-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-partial-trailing-xref.xml) /Desc (Current partial-trailing xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(40, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R >>');

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
    . "40 2\n"
    . $xrefRow($offsets[40])
    . "malformed second trailing subsection row after one decoy row\n"
    . "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

if (!str_contains($plainText, 'Current partial-trailing xref page')) {
    throw new RuntimeException('Expected current partial-trailing xref page text.');
}
if (str_contains($plainText, 'Stale partial-trailing xref page') || str_contains($encodedMetadata, 'Stale Partial-Trailing')) {
    throw new RuntimeException('Stale partial-trailing xref data leaked into import output.');
}
if (($files[0]['name'] ?? null) !== 'current-partial-trailing-xref.xml') {
    throw new RuntimeException('Expected current partial-trailing EmbeddedFiles attachment.');
}

echo '<!-- markerpdf-xref-classic-rebuild-partial-trailing-subsection-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-partial-trailing-subsection-currentbase',
    'native_boundary' => 'classic xref rebuild preserves completed current subsections when a later trailing subsection is partially malformed',
    'current_xref_after_previous_xref' => $currentXrefOffset > $previousXrefOffset,
    'current_text_selected' => str_contains($plainText, 'Current partial-trailing xref page'),
    'current_metadata_selected' => ($metadata['title'] ?? null) === 'Current Partial-Trailing XRef Title',
    'current_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Partial-Trailing XRef Info',
    'current_attachment_selected' => ($files[0]['name'] ?? null) === 'current-partial-trailing-xref.xml',
    'attachment_preflight_uses_current_root' => ($attachmentSummary['filenames'][0] ?? null) === 'current-partial-trailing-xref.xml',
    'stale_prev_text_excluded' => !str_contains($plainText, 'Stale partial-trailing xref page'),
    'stale_prev_metadata_excluded' => !str_contains($encodedMetadata, 'Stale Partial-Trailing'),
    'stale_prev_attachment_excluded' => !str_contains($encodedFiles, 'stale-partial-trailing-xref')
        && !str_contains($encodedAttachmentSummary, 'stale-partial-trailing-xref'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
