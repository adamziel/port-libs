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
        . '<xmp:CreateDate>2026-06-08T17:41:26Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$objectStream = static function (array $members): array {
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress truncated-index object stream smoke fixture.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'count' => count($members),
        'content' => $compressed,
    ];
};

$payload = '<wp-export><post id="truncated-index-smoke"/></wp-export>';
$xmp = gzcompress($xmpPacket(
    'Truncated Index Smoke Title',
    'Partial xref-stream rows must not select WordPress metadata'
));
if (!is_string($xmp)) {
    throw new RuntimeException('Unable to compress truncated-index XMP smoke fixture.');
}

$carrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
    6 => '<< /Title (Truncated Index Smoke Info) /Author (Truncated Index Smoke Author) >>',
    8 => '<< /Names [(truncated-index-smoke.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (truncated-index-smoke.xml) /Desc (Truncated index smoke source) /AFRelationship /Source /EF << /F 11 0 R >> >>',
]);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(2, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(7, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream");
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($payload) . ' /CheckSum <' . md5($payload) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
$addObject(20, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");

$xrefRows = ''
    . $row(2, 20, $carrier['indexes'][1])
    . $row(1, $offsets[2])
    . $row(0, 0, 0)
    . $row(0, 0, 0)
    . $row(0, 0, 0)
    . $row(2, 20, $carrier['indexes'][6])
    . $row(1, $offsets[7])
    . $row(2, 20, $carrier['indexes'][8])
    . $row(0, 0, 0)
    . $row(2, 20, $carrier['indexes'][10]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress truncated-index xref smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Index [1 11] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$review = (new PdfTextExtractor())->extractXrefObjectStreamIndexReview($pdf);

if (
    ($metadata['source'] ?? null) !== []
    || $files !== []
    || ($summary['attachment_count'] ?? null) !== 0
    || ($review['malformed_xref_stream_row_alignment_count'] ?? null) !== 1
    || (($review['malformed_xref_stream_row_alignment_entries'][0]['owner_policy'] ?? null) !== 'truncated_xref_stream_index_rows')
) {
    throw new RuntimeException('Expected truncated xref-stream Index rows to fail closed before metadata and attachments.');
}

echo "<!-- markerpdf-xref-stream-truncated-index-metadata-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'explicit xref-stream Index row count rejected before object-stream metadata extraction',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'metadata_sources' => $metadata['source'],
    'attachment_count' => $summary['attachment_count'],
    'malformed_xref_stream_row_alignment_count' => $review['malformed_xref_stream_row_alignment_count'],
    'owner_policy' => $review['malformed_xref_stream_row_alignment_entries'][0]['owner_policy'] ?? null,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo "<li>No WordPress metadata or attachment rows imported from truncated xref-stream object-stream rows.</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
