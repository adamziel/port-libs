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
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T11:29:40Z</xmp:CreateDate>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$objectStream = static function (array $members): array {
    $pairs = [];
    $indexes = [];
    $body = '';
    foreach ($members as $objectNumber => $memberBody) {
        $pairs[] = $objectNumber . ' ' . strlen($body);
        $indexes[$objectNumber] = count($indexes);
        $body .= $memberBody . "\n";
    }

    $header = implode(' ', $pairs);
    $content = gzcompress($header . "\n" . $body);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to compress omitted-carrier object stream.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $content,
    ];
};

$currentPayload = '<wp-export><post id="current-omitted-prev-carrier"/></wp-export>';
$stalePayload = '<wp-export><post id="stale-omitted-prev-carrier"/></wp-export>';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current omitted Prev carrier page) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale omitted Prev carrier page) Tj ET';
$currentXmp = gzcompress($xmpPacket('Current Omitted Prev Carrier XMP Title', 'Current compressed catalog selected after stale carrier row'));
$staleXmp = gzcompress($xmpPacket('Stale Omitted Prev Carrier XMP Title', 'Stale carrier row must not select metadata'));
if (!is_string($currentXmp) || !is_string($staleXmp)) {
    throw new RuntimeException('Unable to compress omitted-carrier XMP streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleMetadataOffset = $addObject(12, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleEmbeddedOffset = $addObject(13, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$staleCarrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 12 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
    6 => '<< /Title (Stale Omitted Prev Carrier Info Title) /Author (Stale Omitted Prev Carrier Author) >>',
    8 => '<< /Names [(stale-omitted-prev-carrier.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (stale-omitted-prev-carrier.xml) /Desc (Stale omitted Prev carrier attachment) /AFRelationship /Source /EF << /F 13 0 R >> >>',
]);
$staleCarrierOffset = $addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . $staleCarrier['count'] . ' /First ' . $staleCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($staleCarrier['content']) . " >>\nstream\n{$staleCarrier['content']}\nendstream"
);

$previousRows = [];
for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
    $previousRows[$objectNumber] = $xrefTableRow(0, $objectNumber === 0 ? 65535 : 0, 'f');
}
$previousRows[2] = $xrefTableRow($stalePagesOffset);
$previousRows[3] = $xrefTableRow($stalePageOffset);
$previousRows[4] = $xrefTableRow($staleContentOffset);
$previousRows[5] = $xrefTableRow($fontOffset);
$previousRows[12] = $xrefTableRow($staleMetadataOffset);
$previousRows[13] = $xrefTableRow($staleEmbeddedOffset);
$previousRows[20] = $xrefTableRow($staleCarrierOffset);

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 21\n"
    . implode('', $previousRows)
    . "trailer\n<< /Size 21 /Root 1 0 R /Info 6 0 R >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentCarrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
    6 => '<< /Title (Current Omitted Prev Carrier Info Title) /Author (Current Omitted Prev Carrier Author) >>',
    8 => '<< /Names [(current-omitted-prev-carrier.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (current-omitted-prev-carrier.xml) /Desc (Current omitted Prev carrier attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
]);
$addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . $currentCarrier['count'] . ' /First ' . $currentCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($currentCarrier['content']) . " >>\nstream\n{$currentCarrier['content']}\nendstream"
);

$rows = ''
    . $xrefStreamRow(2, 20, $currentCarrier['indexes'][1])
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(2, 20, $currentCarrier['indexes'][6])
    . $xrefStreamRow(1, $currentMetadataOffset, 0)
    . $xrefStreamRow(2, 20, $currentCarrier['indexes'][8])
    . $xrefStreamRow(0, 0, 0)
    . $xrefStreamRow(2, 20, $currentCarrier['indexes'][10])
    . $xrefStreamRow(1, $currentEmbeddedOffset, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress omitted-carrier xref rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 11] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\nstartxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachments = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
$encodedAttachments = json_encode($attachments, JSON_UNESCAPED_SLASHES);

$checks = [
    'current_metadata_title' => ($metadata['title'] ?? null) === 'Current Omitted Prev Carrier XMP Title',
    'current_info_title' => ($metadata['info']['Title'] ?? null) === 'Current Omitted Prev Carrier Info Title',
    'current_catalog_language' => ($metadata['language'] ?? null) === 'en-US',
    'current_embedded_filename' => count($files) === 1 && ($files[0]['filename'] ?? null) === 'current-omitted-prev-carrier.xml',
    'current_attachment_summary' => ($attachments['filenames'] ?? []) === ['current-omitted-prev-carrier.xml'],
    'current_text' => str_contains($text, 'Current omitted Prev carrier page'),
    'stale_metadata_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Omitted Prev Carrier'),
    'stale_embedded_excluded' => is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-omitted-prev-carrier'),
    'stale_attachment_excluded' => is_string($encodedAttachments) && !str_contains($encodedAttachments, 'stale-omitted-prev-carrier'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo "<!-- markerpdf-xref-object-stream-omitted-prev-carrier " . htmlspecialchars(json_encode($checks, JSON_UNESCAPED_SLASHES)) . " -->\n";
echo "<p>Current object-stream metadata and attachment dictionaries are selected when the current xref stream omits the carrier row over a stale Prev carrier.</p>\n";

foreach ($checks as $name => $passed) {
    if (!str_starts_with($name, 'executes_') && $passed !== true) {
        exit(1);
    }
}
