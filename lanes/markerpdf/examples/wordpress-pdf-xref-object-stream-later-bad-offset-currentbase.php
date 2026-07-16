<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = "Title,Status\nCurrent Later Offset Boundary,Ready\n";
$checksum = md5($payload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Current later bad offset object-stream page) Tj ET';
$fileSpec = '<< /Type /Filespec /F (current-later-offset.csv) /Desc (Current compressed FileSpec survives later bad offset) /AFRelationship /Source /EF << /F 9 0 R >> >>';
$badOffset = strpos($fileSpec, '/EF <<');
if ($badOffset === false) {
    throw new RuntimeException('Unable to locate malformed later object-stream member offset.');
}

$laterMember = '<< /Type /Review /Note (valid later object-stream member after bad offset) >>';
$laterOffset = strlen($fileSpec . "\n");
$header = '4 0 12 ' . $badOffset . ' 16 ' . $laterOffset;
$objectStream = gzcompress($header . "\n" . $fileSpec . "\n" . $laterMember . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress later-bad-offset object stream smoke fixture.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
$addObject(2, '<< /Names [(current-later-offset.csv) 4 0 R] >>');
$addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(7, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
$addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605150345Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }

    if ($objectNumber === 4) {
        $rows .= $xrefRow(2, 6, 0);
        continue;
    }

    if ($objectNumber === 12) {
        $rows .= $xrefRow(2, 6, 1);
        continue;
    }

    if ($objectNumber === 16) {
        $rows .= $xrefRow(2, 6, 2);
        continue;
    }

    if ($objectNumber === 20) {
        $rows .= $xrefRow(1, $xrefOffset, 0);
        continue;
    }

    $rows .= isset($offsets[$objectNumber])
        ? $xrefRow(1, $offsets[$objectNumber], 0)
        : $xrefRow(0, 0, 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress later-bad-offset xref stream smoke fixture.');
}

$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$attachment = $summary['attachments'][0] ?? null;
$badEntry = $entries[12] ?? [];
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$smoke = [
    'native_boundary' => 'malformed later object-stream offsets do not truncate earlier xref-selected FileSpec members',
    'current_attachment_kept' => ($summary['attachment_count'] ?? null) === 1
        && is_array($attachment)
        && ($attachment['filename'] ?? null) === 'current-later-offset.csv',
    'valid_filespec_not_truncated' => is_array($attachment)
        && ($attachment['description'] ?? null) === 'Current compressed FileSpec survives later bad offset'
        && ($attachment['stream_object_id'] ?? null) === 9,
    'later_bad_offset_excluded' => ($badEntry['selection_policy'] ?? null) === 'invalid_object_stream_member_offset'
        && ($badEntry['invalid_member_offset_rejected'] ?? null) === true,
    'embedded_payload_available_to_attachment_review' => ($embedded[0]['checksum'] ?? null) === $checksum,
    'payload_bytes_omitted_from_summary' => is_array($attachment)
        && !array_key_exists('bytes', $attachment)
        && !str_contains($summaryJson, $payload),
    'later_member_excluded_from_attachment_review' => !str_contains($summaryJson, 'valid later object-stream member')
        && !str_contains($embeddedJson, 'valid later object-stream member'),
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'current_attachment_kept',
    'valid_filespec_not_truncated',
    'later_bad_offset_excluded',
    'embedded_payload_available_to_attachment_review',
    'payload_bytes_omitted_from_summary',
    'later_member_excluded_from_attachment_review',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Later bad object-stream offset smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-later-bad-offset-currentbase-smoke ' . htmlspecialchars(json_encode(
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
