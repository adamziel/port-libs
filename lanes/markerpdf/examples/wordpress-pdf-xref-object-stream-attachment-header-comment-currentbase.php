<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = '<wp-export><post id="comment-header-filespec"/></wp-export>';
$stalePayload = '<wp-export><post id="stale-comment-header"/></wp-export>';
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);

$decoyMember = '<< /Type /Filespec /F (comment-header-decoy.xml) /Desc (Comment header decoy FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>';
$currentFileSpec = '<< /Type /Filespec /F (comment-header-current.xml) /Desc (Current comment-header FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>';
$currentOffset = strlen($decoyMember . "\n");
$objectStreamHeader = '12 0 % 99 123 fake commented object-stream header row' . "\n" . '4 ' . $currentOffset . ' ';
$objectStreamBytes = $objectStreamHeader . "\n" . $decoyMember . "\n" . $currentFileSpec . "\n";
$compressedObjectStream = gzcompress($objectStreamBytes);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress comment-header attachment object stream fixture.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
$addObject(2, '<< /Names [(comment-header-current.xml) 4 0 R] >>');
$addObject(3, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(4, '<< /Type /Filespec /F (stale-comment-header.xml) /Desc (Stale comment-header FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>');
$addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605082954Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(6, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");
$addObject(20, '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader . "\n") . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= pack('CNn', 0, 0, 65535);
        continue;
    }
    if ($objectNumber === 4) {
        $rows .= pack('CNn', 2, 20, 1);
        continue;
    }
    if ($objectNumber === 21) {
        $rows .= pack('CNn', 1, $xrefOffset, 0);
        continue;
    }
    if (isset($offsets[$objectNumber])) {
        $rows .= pack('CNn', 1, $offsets[$objectNumber], 0);
        continue;
    }

    $rows .= pack('CNn', 0, 0, 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress comment-header attachment xref fixture.');
}

$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$review = (new PdfTextExtractor())->extractXrefObjectStreamIndexReview($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;

$smoke = [
    'native_boundary' => 'xref-stream type-2 FileSpec attachment object-stream header comments',
    'commented_header_filespec_selected' => is_array($attachment)
        && ($attachment['filename'] ?? null) === 'comment-header-current.xml'
        && ($attachment['description'] ?? null) === 'Current comment-header FileSpec',
    'explicit_member_index_preserved' => (($review['entries'][0]['xref_member_index'] ?? null) === 1)
        && (($review['entries'][0]['actual_member_index'] ?? null) === 1),
    'stale_direct_filespec_excluded' => !str_contains($summaryJson, 'stale-comment-header.xml'),
    'comment_decoy_filespec_excluded' => !str_contains($summaryJson, 'comment-header-decoy.xml'),
    'payload_bytes_omitted' => !str_contains($summaryJson, $currentPayload) && !str_contains($summaryJson, $stalePayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'commented_header_filespec_selected',
    'explicit_member_index_preserved',
    'stale_direct_filespec_excluded',
    'comment_decoy_filespec_excluded',
    'payload_bytes_omitted',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Commented object-stream header attachment smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-attachment-header-comment-currentbase-smoke ' . htmlspecialchars(json_encode(
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
