<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-07T14:17:32Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale forward Prev page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current forward Prev page) Tj T* (Repaired stale xref-stream rows) Tj ET';
$staleAttachment = '<wp-export><post id="stale-forward-prev"/></wp-export>';
$currentAttachment = '<wp-export><post id="current-forward-prev"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Forward Prev XMP Title', 'Stale xref-stream rows should not win'));
$currentXmp = gzcompress($xmpPacket('Current Forward Prev XMP Title', 'Current rows survive forward Prev repair'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress xref forward-Prev smoke streams.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names 8 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Stale Forward Prev Info Title) /Author (Stale Forward Author) /Producer (Stale Forward Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$addObject(8, 0, '<< /EmbeddedFiles << /Names [(stale-forward-prev.xml) 10 0 R] >> >>');
$addObject(10, 0, '<< /Type /Filespec /F (stale-forward-prev.xml) /UF (stale-forward-prev.xml) /Desc (Stale forward Prev attachment) /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleAttachment) . " >>\nstream\n{$staleAttachment}\nendstream");

$staleOffsets = $offsets;
$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleOffsets['1:0'])
    . $xrefTableRow($staleOffsets['2:0'])
    . $xrefTableRow($staleOffsets['3:0'])
    . $xrefTableRow($staleOffsets['4:0'])
    . $xrefTableRow($staleOffsets['5:0'])
    . $xrefTableRow($staleOffsets['6:0'])
    . $xrefTableRow($staleOffsets['7:0'])
    . $xrefTableRow($staleOffsets['8:0'])
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleOffsets['10:0'])
    . $xrefTableRow($staleOffsets['11:0'])
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names 8 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Title (Current Forward Prev Info Title) /Author (Current Forward Author) /Producer (Current Forward Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /EmbeddedFiles << /Names [(current-forward-prev.xml) 10 0 R] >> >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-forward-prev.xml) /UF (current-forward-prev.xml) /Desc (Current forward Prev attachment) /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentAttachment) . " >>\nstream\n{$currentAttachment}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $staleOffsets['1:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['2:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['3:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['4:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['5:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['6:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['7:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['8:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['10:0'], 0)
    . $xrefStreamRow(1, $staleOffsets['11:0'], 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress current xref-stream forward-Prev smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$forwardPrev = $currentXrefOffset + 7;
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $forwardPrev . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-forward-prev-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream current rows with stale offsets use repaired prior xref base when /Prev points forward',
    'forward_prev_repaired' => $forwardPrev > $currentXrefOffset,
    'current_text_selected' => str_contains($plainText, 'Current forward Prev page'),
    'stale_text_excluded' => !str_contains($plainText, 'Stale forward Prev page'),
    'current_metadata_selected' => ($metadata['title'] ?? null) === 'Current Forward Prev XMP Title',
    'stale_metadata_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Forward Prev'),
    'current_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-forward-prev.xml',
    'stale_attachment_excluded' => is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-forward-prev'),
    'attachment_count' => $summary['attachment_count'],
    'total_attachment_bytes' => $summary['total_bytes'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF xref repair'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($files as $file) {
    echo "<!-- wp:list -->\n";
    echo '<ul><li>' . htmlspecialchars((string) $file['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ': '
        . htmlspecialchars((string) $file['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li></ul>\n";
    echo "<!-- /wp:list -->\n\n";
}
