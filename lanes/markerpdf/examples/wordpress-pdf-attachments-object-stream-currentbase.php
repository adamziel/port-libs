<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = '<wp-export><post id="compressed-filespec"/></wp-export>';
$stalePayload = '<wp-export><post id="stale-direct-filespec"/></wp-export>';
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
$addObject(2, '<< /Names [(compressed-source.xml) 4 0 R] >>');
$addObject(3, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(4, '<< /Type /Filespec /F (stale-direct-source.xml) /Desc (Stale direct FileSpec) /AFRelationship /Alternative /EF << /F 6 0 R >> >>');
$addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605011736Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(6, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$compressedFileSpec = '<< /Type /Filespec /F (compressed-source.xml) /Desc (Current compressed FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>';
$objectStreamHeader = '4 0 ';
$objectStreamBytes = $objectStreamHeader . $compressedFileSpec;
$compressedObjectStream = gzcompress($objectStreamBytes);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress attachment object stream fixture.');
}
$addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= pack('CNn', 0, 0, 65535);
        continue;
    }
    if ($objectNumber === 4) {
        $rows .= pack('CNn', 2, 20, 0);
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
    throw new RuntimeException('Unable to compress attachment object stream xref fixture.');
}

$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
if (!is_array($attachment)
    || ($attachment['filename'] ?? null) !== 'compressed-source.xml'
    || ($attachment['description'] ?? null) !== 'Current compressed FileSpec'
    || ($attachment['associated_file_source'] ?? null) !== 'catalog_af'
    || ($attachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $currentPayload)
    || str_contains($summaryJson, $stalePayload)
    || str_contains($summaryJson, 'stale-direct-source.xml')
) {
    throw new RuntimeException('Expected compressed FileSpec attachment summary without direct stale rows or payload bytes.');
}

echo "<!-- markerpdf-pdf-attachment-object-stream-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'xref-stream type-2 object-stream FileSpec attachment preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'object_stream_filespec_selected' => ($attachment['file_spec_object_id'] ?? null) === 4,
    'embedded_file_stream_object' => $attachment['stream_object_id'] ?? null,
    'stale_direct_filespec_excluded' => !str_contains($summaryJson, 'stale-direct-source.xml'),
    'payload_bytes_omitted' => !str_contains($summaryJson, $currentPayload) && !str_contains($summaryJson, $stalePayload),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        (string) $attachment['filename']
        . ' (' . (string) $attachment['source']
        . ', ' . (string) ($attachment['relationship'] ?? 'unassociated')
        . ', ' . (string) $attachment['content_type']
        . ', ' . (string) $attachment['byte_length'] . ' bytes)',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
