<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = "Title,Status\nCurrent Attachment,Ready\n";
$stalePayload = "Title,Status\nNested Dictionary Leak,Ignore\n";
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Current nested-dictionary attachment page) Tj ET';
$fakeFileSpec = '<< /Type /Filespec /F (nested-stale.csv) /Desc (Nested dictionary stale FileSpec) /AFRelationship /Alternative /EF << /F 5 0 R >> >>';
$carrierBody = '<< /Type /Catalog /Pages 3 0 R /PieceInfo << /WP << /Private ' . $fakeFileSpec . ' >> >> >>';
$objectData = $carrierBody . "\n";
$badOffset = strpos($objectData, $fakeFileSpec);
if ($badOffset === false) {
    throw new RuntimeException('Unable to locate nested dictionary FileSpec decoy.');
}

$header = '12 0 4 ' . $badOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress nested dictionary attachment object stream.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [8 0 R] >>');
$addObject(2, 0, '<< /Names [(current.csv) 8 0 R (nested-stale.csv) 4 0 R] >>');
$addObject(3, 0, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
$addObject(5, 0, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(7, 0, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 10 0 R >>');
$addObject(8, 0, '<< /Type /Filespec /F (current.csv) /Desc (Current direct FileSpec) /AFRelationship /Source /EF << /F 9 0 R >> >>');
$addObject(9, 0, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605063544Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(11, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 1)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['7:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0'])
    . $xrefRow(1, $offsets['11:0'])
    . $xrefRow(2, 6, 0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress nested dictionary attachment xref stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 11 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;

$smoke = [
    'native_boundary' => 'xref-selected object-stream FileSpec members must start on object boundaries, not inside nested dictionaries',
    'current_attachment_kept' => ($summary['attachment_count'] ?? null) === 1
        && is_array($attachment)
        && ($attachment['filename'] ?? null) === 'current.csv',
    'nested_dictionary_filespec_excluded' => !str_contains($summaryJson, 'nested-stale.csv')
        && !str_contains($embeddedJson, 'nested-stale.csv'),
    'stale_payload_excluded_from_review' => !str_contains($summaryJson, $stalePayload)
        && !str_contains($embeddedJson, $stalePayload),
    'payload_bytes_omitted_from_summary' => is_array($attachment) && !array_key_exists('bytes', $attachment),
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
    'member_offset_token_boundary' => $entry['member_offset_token_boundary'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'current_attachment_kept',
    'nested_dictionary_filespec_excluded',
    'stale_payload_excluded_from_review',
    'payload_bytes_omitted_from_summary',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Nested dictionary attachment object-stream smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['selection_policy'] ?? null) !== 'invalid_object_stream_member_offset') {
    throw new RuntimeException('Expected invalid object-stream member offset review.');
}

echo '<!-- markerpdf-xref-object-stream-attachment-offset-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-checksum="'
    . htmlspecialchars((string) ($attachment['checksum_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        (string) ($attachment['filename'] ?? 'attachment')
        . ' - ' . (string) ($attachment['relationship'] ?? 'unassociated')
        . ', ' . (string) ($attachment['content_type'] ?? 'application/octet-stream')
        . ', ' . (string) ($attachment['byte_length'] ?? 0) . ' bytes',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
