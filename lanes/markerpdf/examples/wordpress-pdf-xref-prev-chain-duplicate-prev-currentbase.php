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
        . '<xmp:CreateDate>2026-06-07T15:41:21Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale duplicate Prev smoke page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate Prev smoke page) Tj T* (Last Prev smoke wins) Tj ET';
$stalePayload = '<wp-export><post id="stale-duplicate-prev-smoke"/></wp-export>';
$currentPayload = '<wp-export><post id="current-duplicate-prev-smoke"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Duplicate Prev Smoke XMP', 'First duplicate Prev target must not win'));
$currentXmp = gzcompress($xmpPacket('Current Duplicate Prev Smoke XMP', 'Last duplicate Prev target selected'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress duplicate-Prev smoke metadata streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Duplicate Prev Smoke Info) /Author (Stale Duplicate Smoke Author) /Producer (Stale Duplicate Smoke Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-duplicate-prev-smoke.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-duplicate-prev-smoke.xml) /Desc (Stale duplicate Prev smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$baseXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleCatalogOffset)
    . $xrefTableRow($stalePagesOffset)
    . $xrefTableRow($stalePageOffset)
    . $xrefTableRow($staleContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($staleInfoOffset)
    . $xrefTableRow($staleMetadataOffset)
    . $xrefTableRow($staleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleFileSpecOffset)
    . $xrefTableRow($staleEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$baseXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Duplicate Prev Smoke Info) /Author (Current Duplicate Smoke Author) /Producer (Current Duplicate Smoke Producer) >>');
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-duplicate-prev-smoke.xml) 10 0 R] >>');
$currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-duplicate-prev-smoke.xml) /Desc (Current duplicate Prev smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$middleRows = ''
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, $currentInfoOffset, 0)
    . $xrefStreamRow(1, $currentMetadataOffset, 0)
    . $xrefStreamRow(1, $currentNameTreeOffset, 0)
    . $xrefStreamRow(1, $currentFileSpecOffset, 0)
    . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
$compressedMiddleRows = gzcompress($middleRows);
if (!is_string($compressedMiddleRows)) {
    throw new RuntimeException('Unable to compress middle duplicate-Prev smoke xref-stream rows.');
}

$middleXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $baseXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMiddleRows) . " >>\n"
    . "stream\n{$compressedMiddleRows}\nendstream\nendobj\n"
    . "startxref\n{$middleXrefOffset}\n%%EOF\n";

$latestRows = $xrefStreamRow(1, 0, 0);
$compressedLatestRows = gzcompress($latestRows);
if (!is_string($compressedLatestRows)) {
    throw new RuntimeException('Unable to compress latest duplicate-Prev smoke xref-stream rows.');
}

$latestXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Prev ' . $baseXrefOffset . ' /Prev ' . $middleXrefOffset . ' /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
    . "stream\n{$compressedLatestRows}\nendstream\nendobj\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encoded = json_encode([$metadata, $files, $summary, $lines], JSON_UNESCAPED_SLASHES);

$status = [
    'duplicate_prev_entries_present' => substr_count($pdf, '/Prev ') >= 3,
    'current_text_selected' => $lines === ['Current duplicate Prev smoke page', 'Last Prev smoke wins'],
    'current_xmp_selected' => ($metadata['title'] ?? null) === 'Current Duplicate Prev Smoke XMP',
    'current_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Duplicate Prev Smoke Info',
    'current_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-duplicate-prev-smoke.xml'
        && ($files[0]['content'] ?? null) === $currentPayload,
    'attachment_preflight_selected' => ($summary['filenames'] ?? []) === ['current-duplicate-prev-smoke.xml']
        && ($summary['total_bytes'] ?? null) === strlen($currentPayload),
    'stale_prev_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Stale Duplicate Prev')
        && !str_contains($encoded, 'stale-duplicate-prev'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($status as $passed) {
    if ($passed !== true && $passed !== false) {
        throw new RuntimeException('Unexpected duplicate-Prev smoke status value.');
    }
}
$requiredTrueStatus = array_diff_key($status, [
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);
if (
    in_array(false, $requiredTrueStatus, true)
    || $status['executes_python_or_models'] !== false
    || $status['executes_external_pdf_tools'] !== false
) {
    throw new RuntimeException('Duplicate xref /Prev WordPress smoke failed: ' . json_encode($status, JSON_UNESCAPED_SLASHES));
}

echo '<!-- wp:comment -->' . htmlspecialchars(json_encode($status, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<!-- /wp:comment -->\n";
echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- ' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
